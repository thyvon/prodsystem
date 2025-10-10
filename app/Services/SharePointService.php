<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SharePointService
{
    protected User $user;
    protected string $accessToken;
    protected string $siteId;
    protected int $chunkSize;

    public function __construct(User $user, int $chunkSize = 10 * 1024 * 1024)
    {
        $this->user = $user;
        $this->chunkSize = $chunkSize;
        $this->siteId = config('services.sharepoint.site_id')
            ?: throw new \InvalidArgumentException('SharePoint site_id not set');
        $this->accessToken = $this->getValidAccessToken();
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
     * FILE UPLOAD (single or multiple)
     * ----------------------------- */

    public function uploadFile(mixed $fileOrFiles, string $folderPath, array $properties = [], ?string $fileName = null, ?string $driveId = null): array
    {
        $driveId = $this->resolveDriveId($driveId);
        $files = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
        $results = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) continue;

            $name = $fileName ?? time() . '_' . $file->getClientOriginalName();
            $fileInfo = $file->getSize() <= 4 * 1024 * 1024
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

        $response = Http::withToken($this->accessToken)
            ->withBody(fopen($file->getRealPath(), 'rb'), $file->getMimeType())
            ->put($url)
            ->throw();

        return $this->extractFileInfo($response);
    }

    protected function uploadLargeFile(UploadedFile $file, string $folderPath, string $fileName, string $driveId): array
    {
        $session = Http::withToken($this->accessToken)
            ->post("https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/root:/{$folderPath}/{$fileName}:/createUploadSession", [
                'item' => ['@microsoft.graph.conflictBehavior' => 'replace']
            ])
            ->throw()
            ->json('uploadUrl');

        $stream = fopen($file->getRealPath(), 'rb');
        $size = $file->getSize();
        $offset = 0;

        while ($offset < $size) {
            $chunk = fread($stream, $this->chunkSize);
            $end = $offset + strlen($chunk) - 1;

            Http::withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$size}"])
                ->put($session, $chunk)
                ->throw();

            $offset += strlen($chunk);
        }

        fclose($stream);

        return [
            'id' => basename($fileName),
            'name' => $fileName,
            'url' => $session
        ];
    }

    /** -----------------------------
     * FILE UPDATE (single or multiple)
     * ----------------------------- */

    public function updateFile(string|array $fileIdOrData, mixed $fileOrFiles, array $properties = [], ?string $driveId = null): array
    {
        $driveId = $this->resolveDriveId($driveId);
        $fileIds = is_array($fileIdOrData) ? $fileIdOrData : [$fileIdOrData];
        $files = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
        $results = [];

        foreach ($fileIds as $index => $fileId) {
            $file = $files[$index] ?? null;
            if (!$file instanceof UploadedFile) continue;

            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content";

            $response = Http::withToken($this->accessToken)
                ->withBody(fopen($file->getRealPath(), 'rb'), $file->getMimeType())
                ->put($url)
                ->throw();

            if (!empty($properties)) {
                $this->updateFileProperties($fileId, $properties, $driveId);
            }

            $results[] = array_merge(
                $this->extractFileInfo($response),
                ['ui_url' => $this->generateUiLink($response->json('webUrl'))]
            );
        }

        return is_array($fileOrFiles) ? $results : $results[0];
    }

    /** -----------------------------
     * FILE PROPERTIES / DELETE / STREAM
     * ----------------------------- */

    public function updateFileProperties(string $fileId, array $properties, ?string $driveId = null): array
    {
        $driveId = $this->resolveDriveId($driveId);
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/listItem/fields";

        return Http::withToken($this->accessToken)->patch($url, $properties)->throw()->json();
    }

    public function deleteFile(mixed $fileIdOrIds, ?string $driveId = null, bool $ignoreNotFound = true): bool|array
    {
        $driveId = $this->resolveDriveId($driveId);
        $fileIds = is_array($fileIdOrIds) ? $fileIdOrIds : [$fileIdOrIds];
        $results = [];

        foreach ($fileIds as $fileId) {
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";
            try {
                Http::withToken($this->accessToken)->delete($url)->throw();
                $results[] = true;
            } catch (\Illuminate\Http\Client\RequestException $e) {
                if (!$ignoreNotFound || ($e->response && $e->response->status() != 404)) {
                    throw $e;
                }
                $results[] = false;
            }
        }

        return is_array($fileIdOrIds) ? $results : $results[0];
    }

    public function streamFile(string $fileId, ?string $driveId = null)
    {
        $driveId = $this->resolveDriveId($driveId);

        $meta = Http::withToken($this->accessToken)
            ->get("https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$fileId}")
            ->throw()
            ->json();

        $content = $this->getFileContent($fileId, $driveId)['body'];

        return response($content, 200)
            ->header('Content-Type', $meta['file']['mimeType'] ?? 'application/octet-stream')
            ->header('Content-Disposition', "inline; filename=\"{$meta['name']}\"");
    }

    public function getFileContent(string $fileId, ?string $driveId = null): array
    {
        $driveId = $this->resolveDriveId($driveId);

        $meta = Http::withToken($this->accessToken)
            ->get("https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}")
            ->throw()
            ->json();

        $response = Http::withToken($this->accessToken)
            ->get("https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content")
            ->throw();

        return [
            'body' => $response->body(),
            'mime' => $meta['file']['mimeType'] ?? 'application/octet-stream'
        ];
    }

    /** -----------------------------
     * HELPERS
     * ----------------------------- */

    protected function extractFileInfo($response): array
    {
        return [
            'id' => $response->json('id'),
            'name' => $response->json('name'),
            'url' => $response->json('webUrl'),
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
