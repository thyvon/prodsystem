<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SharePointService
{
    protected User $user;
    protected string $accessToken;
    protected string $siteUrl;
    protected int $chunkSize;
    protected Client $guzzle;
    protected int $maxRetries = 3;

    public function __construct(User $user, int $chunkSize = 50 * 1024 * 1024)
    {
        $this->user = $user;
        $this->chunkSize = $chunkSize;
        $this->siteUrl = config('services.sharepoint.site_url') 
            ?: throw new \InvalidArgumentException('SharePoint site_url not set');
        $this->guzzle = new Client(['timeout' => 0, 'connect_timeout' => 0]);
    }

    /** -----------------------------
     * TOKEN MANAGEMENT
     * ----------------------------- */
    protected function getAccessToken(): string
    {
        if (!$this->user->microsoft_token || !$this->user->microsoft_refresh_token) {
            Log::warning('Missing Microsoft tokens', ['user_id' => $this->user->id]);
            return $this->forceMicrosoftLogin();
        }

        $expiresAt = $this->user->microsoft_token_expires_at
            ? Carbon::parse($this->user->microsoft_token_expires_at)
            : null;

        if (!$expiresAt || Carbon::now()->addMinutes(5)->gte($expiresAt)) {
            return $this->refreshAccessToken();
        }

        return $this->user->microsoft_token;
    }

    protected function refreshAccessToken(): string
    {
        try {
            $tenantId = config('services.microsoft.tenant_id');
            $clientId = config('services.microsoft.client_id');
            $clientSecret = config('services.microsoft.client_secret');

            $response = $this->guzzle->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->user->microsoft_refresh_token,
                    'scope' => 'User.Read Sites.ReadWrite.All offline_access',
                ],
                'timeout' => 15,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['access_token'])) {
                Log::warning('Microsoft token refresh failed', ['user_id' => $this->user->id, 'body' => $data]);
                return $this->forceMicrosoftLogin();
            }

            $this->user->update([
                'microsoft_token' => $data['access_token'],
                'microsoft_refresh_token' => $data['refresh_token'] ?? $this->user->microsoft_refresh_token,
                'microsoft_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            Log::info("Microsoft token refreshed successfully", ['user_id' => $this->user->id]);
            return $data['access_token'];
        } catch (\Throwable $e) {
            Log::error('Microsoft token refresh exception', ['user_id' => $this->user->id, 'message' => $e->getMessage()]);
            return $this->forceMicrosoftLogin();
        }
    }

    protected function forceMicrosoftLogin(): never
    {
        $this->user->update([
            'microsoft_token' => null,
            'microsoft_refresh_token' => null,
            'microsoft_token_expires_at' => null,
        ]);

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        redirect()->route('microsoft.login')->send();
        exit;
    }

    /** -----------------------------
     * FILE UPLOAD (Single or Multiple) with progress callback
     * ----------------------------- */
    public function uploadFile(
        mixed $fileOrFiles, 
        string $libraryServerRelativePath, 
        ?string $fileName = null, 
        array $metadata = [],
        ?callable $progressCallback = null
    ): array {
        $this->accessToken = $this->getAccessToken();
        $files = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
        $results = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) continue;

            $name = $fileName ?? time() . '_' . $file->getClientOriginalName();

            $fileInfo = $file->getSize() <= 4 * 1024 * 1024
                ? $this->uploadSmallFile($file, $libraryServerRelativePath, $name, $progressCallback)
                : $this->uploadLargeFile($file, $libraryServerRelativePath, $name, $progressCallback);

            if (!empty($metadata)) {
                $this->updateFileProperties($libraryServerRelativePath, $name, $metadata);
            }

            $results[] = $fileInfo;
        }

        return is_array($fileOrFiles) ? $results : $results[0];
    }

    protected function uploadSmallFile(
        UploadedFile $file, 
        string $libraryServerRelativePath, 
        string $fileName, 
        ?callable $progressCallback = null
    ): array {
        $url = $this->siteUrl . "/_api/web/GetFolderByServerRelativeUrl('{$libraryServerRelativePath}')/Files/add(url='{$fileName}',overwrite=true)";

        $response = $this->guzzle->post($url, [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json;odata=verbose',
            ],
            'body' => fopen($file->getRealPath(), 'rb'),
        ]);

        if ($progressCallback) $progressCallback($file->getSize(), $file->getSize());

        $data = json_decode($response->getBody()->getContents(), true)['d'] ?? [];

        return [
            'id' => $data['UniqueId'] ?? null,
            'name' => $data['Name'] ?? $fileName,
            'url' => $data['ServerRelativeUrl'] ?? null,
        ];
    }

    /** -----------------------------
     * LARGE FILE UPLOAD WITH CHUNKING AND PROGRESS
     * ----------------------------- */
    protected function uploadLargeFile(
        UploadedFile $file, 
        string $libraryServerRelativePath, 
        string $fileName, 
        ?callable $progressCallback = null
    ): array {
        $this->accessToken = $this->getAccessToken();
        $uploadId = Str::uuid()->toString();
        $stream = fopen($file->getRealPath(), 'rb');
        $size = $file->getSize();
        $offset = 0;

        try {
            // Start upload
            $chunk = fread($stream, min($this->chunkSize, $size));
            $startUrl = $this->siteUrl . "/_api/web/getfolderbyserverrelativeurl('{$libraryServerRelativePath}')/Files/add(url='{$fileName}',overwrite=true)/StartUpload(uploadId=guid'{$uploadId}')";
            $this->retryRequest(fn() => $this->guzzle->post($startUrl, [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}", 'Accept' => 'application/json;odata=verbose'],
                'body' => $chunk,
            ]), $size);

            $offset = strlen($chunk);
            if ($progressCallback) $progressCallback($offset, $size);

            // Continue chunks
            while ($offset < $size) {
                $chunk = fread($stream, $this->chunkSize);
                $chunkLength = strlen($chunk);
                $continueUrl = $this->siteUrl . "/_api/web/getfilebyserverrelativeurl('{$libraryServerRelativePath}/{$fileName}')/ContinueUpload(uploadId=guid'{$uploadId}',fileOffset={$offset})";

                $this->retryRequest(fn() => $this->guzzle->post($continueUrl, [
                    'headers' => ['Authorization' => "Bearer {$this->accessToken}", 'Accept' => 'application/json;odata=verbose'],
                    'body' => $chunk,
                ]), $size);

                $offset += $chunkLength;
                if ($progressCallback) $progressCallback($offset, $size);
            }

            fclose($stream);

            // Finish upload
            $finishUrl = $this->siteUrl . "/_api/web/getfilebyserverrelativeurl('{$libraryServerRelativePath}/{$fileName}')/FinishUpload(uploadId=guid'{$uploadId}',fileOffset={$offset})";
            $this->guzzle->post($finishUrl, [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}", 'Accept' => 'application/json;odata=verbose'],
            ]);

            if ($progressCallback) $progressCallback($size, $size);

            return ['id' => null, 'name' => $fileName, 'url' => "{$libraryServerRelativePath}/{$fileName}"];
        } catch (\Throwable $e) {
            fclose($stream);
            Log::error('Large file upload failed', ['user_id' => $this->user->id, 'file' => $fileName, 'message' => $e->getMessage()]);
            throw $e;
        }
    }

    /** -----------------------------
     * RETRY HELPER
     * ----------------------------- */
    protected function retryRequest(callable $callback, int $size): int
    {
        $attempts = 0;
        do {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $attempts++;
                Log::warning("Chunk upload failed, retrying ($attempts/{$this->maxRetries})", ['message' => $e->getMessage()]);
                if ($attempts >= $this->maxRetries) throw $e;
                sleep(1);
            }
        } while ($attempts < $this->maxRetries);

        return 0;
    }

    /** -----------------------------
     * FILE UPDATE
     * ----------------------------- */
    public function updateFile(string $libraryServerRelativePath, string $fileName, UploadedFile $file, array $metadata = []): array
    {
        $this->accessToken = $this->getAccessToken();
        $url = $this->siteUrl . "/_api/web/GetFileByServerRelativeUrl('{$libraryServerRelativePath}/{$fileName}')/\$value";

        $this->guzzle->put($url, [
            'headers' => ['Authorization' => "Bearer {$this->accessToken}", 'Content-Type' => $file->getMimeType()],
            'body' => fopen($file->getRealPath(), 'rb'),
        ]);

        if (!empty($metadata)) {
            $this->updateFileProperties($libraryServerRelativePath, $fileName, $metadata);
        }

        return ['name' => $fileName, 'url' => "{$libraryServerRelativePath}/{$fileName}"];
    }

    /** -----------------------------
     * FILE METADATA / PROPERTIES
     * ----------------------------- */
    public function updateFileProperties(string $libraryServerRelativePath, string $fileName, array $metadata): array
    {
        $this->accessToken = $this->getAccessToken();
        $url = $this->siteUrl . "/_api/web/GetFileByServerRelativeUrl('{$libraryServerRelativePath}/{$fileName}')/ListItemAllFields";

        $response = $this->guzzle->post($url, [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json;odata=verbose',
                'Content-Type' => 'application/json;odata=verbose',
                'X-HTTP-Method' => 'MERGE',
                'IF-MATCH' => '*',
            ],
            'json' => $metadata,
        ]);

        return json_decode($response->getBody()->getContents(), true)['d'] ?? [];
    }

    /** -----------------------------
     * FILE DELETE
     * ----------------------------- */
    public function deleteFile(string $libraryServerRelativePath, string $fileName): bool
    {
        $this->accessToken = $this->getAccessToken();
        $url = $this->siteUrl . "/_api/web/GetFileByServerRelativeUrl('{$libraryServerRelativePath}/{$fileName}')";
        $this->guzzle->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'X-HTTP-Method' => 'DELETE',
                'IF-MATCH' => '*',
            ],
        ]);
        return true;
    }

    /** -----------------------------
     * FILE STREAM
     * ----------------------------- */
    public function streamFile(string $libraryServerRelativePath, string $fileName)
    {
        $this->accessToken = $this->getAccessToken();
        $url = $this->siteUrl . "/_api/web/GetFileByServerRelativeUrl('{$libraryServerRelativePath}/{$fileName}')/\$value";

        $response = $this->guzzle->get($url, ['headers' => ['Authorization' => "Bearer {$this->accessToken}"]]);

        $mimeType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf' ? 'application/pdf' : 'application/octet-stream';

        return response($response->getBody()->getContents(), 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', "inline; filename=\"{$fileName}\"")
            ->header('Accept-Ranges', 'bytes');
    }
}
