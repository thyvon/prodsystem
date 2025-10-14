<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class SharePointService
{
    protected User $user;
    protected string $accessToken;
    protected string $siteId;
    protected int $chunkSize;
    protected Client $guzzle;

    public function __construct(User $user, int $chunkSize = 50 * 1024 * 1024) // 50MB chunks
    {
        $this->user = $user;
        $this->chunkSize = $chunkSize;
        $this->siteId = config('services.sharepoint.site_id')
            ?: throw new \InvalidArgumentException('SharePoint site_id not set');
        $this->accessToken = $this->getValidAccessToken();

        $this->guzzle = new Client([
            'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
            'http_errors' => true,
            'timeout' => 300,
        ]);
    }

    /** -----------------------------
     * TOKEN MANAGEMENT
     * ----------------------------- */
    
    protected function getValidAccessToken(): string
    {
        if (!$this->user->microsoft_token || !$this->user->microsoft_refresh_token) {
            throw new \RuntimeException('User does not have Microsoft tokens.');
        }

        $expiresAt = $this->user->microsoft_token_expires_at
            ? Carbon::parse($this->user->microsoft_token_expires_at)
            : null;

        if (!$expiresAt || Carbon::now()->greaterThanOrEqualTo($expiresAt->subMinutes(5))) {
            return $this->refreshAccessToken();
        }

        return $this->user->microsoft_token;
    }

    protected function refreshAccessToken(): string
    {
        try {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/" . config('services.microsoft.tenant_id') . "/oauth2/v2.0/token",
                [
                    'client_id' => config('services.microsoft.client_id'),
                    'client_secret' => config('services.microsoft.client_secret'),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->user->microsoft_refresh_token,
                    'scope' => 'User.Read Files.ReadWrite.All Sites.ReadWrite.All offline_access',
                ]
            );

            if ($response->failed()) {
                Log::error('Microsoft token refresh failed', [
                    'user_id' => $this->user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Failed to refresh Microsoft access token.');
            }

            $data = $response->json();
            $this->user->update([
                'microsoft_token' => $data['access_token'] ?? $this->user->microsoft_token,
                'microsoft_refresh_token' => $data['refresh_token'] ?? $this->user->microsoft_refresh_token,
                'microsoft_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            return $this->user->microsoft_token;
        } catch (\Throwable $e) {
            Log::error('Microsoft token refresh exception', [
                'user_id' => $this->user->id,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Microsoft access token refresh failed.');
        }
    }

    /** -----------------------------
     * FILE UPLOAD
     * ----------------------------- */

    public function uploadFile(mixed $fileOrFiles, string $folderPath, array $properties = [], ?string $fileName = null, ?string $driveId = null): array
    {
        $driveId = $this->resolveDriveId($driveId);
        $files = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
        $results = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) continue;

            $name = $fileName ?? time() . '_' . $file->getClientOriginalName();

            $fileInfo = $file->getSize() <= 50 * 1024 * 1024
                ? $this->uploadSmallFile($file, $folderPath, $name, $driveId)
                : $this->uploadLargeFile($file, $folderPath, $name, $driveId);

            if (!empty($properties)) {
                $this->updateFileProperties($fileInfo['id'], $properties, $driveId);
            }

            $fileInfo['ui_url'] = $this->generateUiLink($fileInfo['url']);
            $results[] = $fileInfo;
        }

        return is_array($fileOrFiles) ? $results : $results[0];
    }

    protected function uploadSmallFile(UploadedFile $file, string $folderPath, string $fileName, string $driveId): array
    {
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/root:/{$folderPath}/{$fileName}:/content";

        $response = $this->guzzle->put($url, [
            'body' => fopen($file->getRealPath(), 'rb'),
            'headers' => ['Content-Type' => $file->getMimeType()]
        ]);

        return $this->extractFileInfo($response);
    }

    protected function uploadLargeFile(UploadedFile $file, string $folderPath, string $fileName, string $driveId): array
    {
        $session = $this->guzzle->post("https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/root:/{$folderPath}/{$fileName}:/createUploadSession", [
            'json' => ['item' => ['@microsoft.graph.conflictBehavior' => 'replace']]
        ])->json('uploadUrl');

        $stream = fopen($file->getRealPath(), 'rb');
        $size = $file->getSize();
        $offset = 0;
        $promises = [];

        while ($offset < $size) {
            $chunk = fread($stream, $this->chunkSize);
            $end = $offset + strlen($chunk) - 1;

            $promises[] = $this->guzzle->putAsync($session, [
                'headers' => ['Content-Range' => "bytes {$offset}-{$end}/{$size}"],
                'body' => $chunk
            ]);

            $offset += strlen($chunk);
        }

        Promise\Utils::settle($promises)->wait();
        fclose($stream);

        return [
            'id' => basename($fileName),
            'name' => $fileName,
            'url' => $session
        ];
    }

    /** -----------------------------
     * OTHER METHODS (update, delete, stream) remain unchanged
     * ----------------------------- */

    // Keep updateFile(), updateFileProperties(), deleteFile(), streamFile(), etc. intact

    protected function extractFileInfo($response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'url' => $data['webUrl'],
        ];
    }

    protected function generateUiLink(string $webUrl): string
    {
        $siteRelativePath = rawurldecode(str_replace('https://mjqeducationplc.sharepoint.com', '', $webUrl));
        $encodedFile = rawurlencode($siteRelativePath);
        $encodedParent = rawurlencode(dirname($siteRelativePath));

        $segments = explode('/', trim($siteRelativePath, '/'));
        $libraryName = $segments[2] ?? $segments[1] ?? $segments[0];

        return "https://mjqeducationplc.sharepoint.com/sites/PRODMJQE/{$libraryName}/Forms/AllItems.aspx?id={$encodedFile}&parent={$encodedParent}&p=true&ga=1";
    }

    protected function resolveDriveId(?string $driveId): string
    {
        return $driveId ?? config('services.sharepoint.drive_id')
            ?: throw new \InvalidArgumentException('SharePoint default drive_id not set');
    }
}
