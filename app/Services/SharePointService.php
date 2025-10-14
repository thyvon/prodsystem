<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;

class SharePointService
{
    protected User $user;
    protected string $accessToken;
    protected string $siteId;
    protected int $defaultChunkSize;
    protected int $maxConcurrency;
    protected Client $guzzle;

    public function __construct(User $user, int $defaultChunkSize = 50 * 1024 * 1024, int $maxConcurrency = 10)
    {
        $this->user = $user;
        $this->defaultChunkSize = $defaultChunkSize;
        $this->maxConcurrency = $maxConcurrency;
        $this->siteId = config('services.sharepoint.site_id')
            ?: throw new \InvalidArgumentException('SharePoint site_id not set');
        $this->accessToken = $this->getValidAccessToken();

        $this->guzzle = new Client([
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
            'timeout' => 7200, // Allow large files to upload
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

    /** -----------------------------
     * LARGE FILE UPLOAD (DYNAMIC CHUNK + HIGH CONCURRENCY)
     * ----------------------------- */
    protected function uploadLargeFile(UploadedFile $file, string $folderPath, string $fileName, string $driveId): array
    {
        $session = $this->guzzle->post(
            "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/root:/{$folderPath}/{$fileName}:/createUploadSession",
            ['json' => ['item' => ['@microsoft.graph.conflictBehavior' => 'replace']]]
        )->json('uploadUrl');

        $size = $file->getSize();
        $chunkSize = $this->calculateChunkSize($size);
        $promises = [];
        $stream = fopen($file->getRealPath(), 'rb');
        $offset = 0;

        while ($offset < $size) {
            $readSize = min($chunkSize, $size - $offset);
            $chunk = fread($stream, $readSize);
            $start = $offset;
            $end = $offset + strlen($chunk) - 1;

            $promises[] = $this->guzzle->putAsync($session, [
                'headers' => ['Content-Range' => "bytes {$start}-{$end}/{$size}"],
                'body' => $chunk
            ]);

            if (count($promises) >= $this->maxConcurrency) {
                Utils::settle($promises)->wait();
                $promises = [];
            }

            $offset += strlen($chunk);
        }

        if (!empty($promises)) {
            Utils::settle($promises)->wait();
        }

        fclose($stream);

        return [
            'id' => basename($fileName),
            'name' => $fileName,
            'url' => $session,
        ];
    }

    protected function calculateChunkSize(int $fileSize): int
    {
        // Dynamic chunk sizing for large files
        if ($fileSize > 1024 * 1024 * 1024) return 100 * 1024 * 1024; // >1GB → 100MB
        if ($fileSize > 500 * 1024 * 1024) return 75 * 1024 * 1024;   // 500MB–1GB → 75MB
        return $this->defaultChunkSize;                                // Default 50MB
    }

    /** -----------------------------
     * UPDATE FILE
     * ----------------------------- */
    public function updateFile(string|array $fileIdOrData, mixed $fileOrFiles, array $properties = [], ?string $driveId = null, ?string $targetFileName = null): array
    {
        $driveId = $this->resolveDriveId($driveId);
        $fileIds = is_array($fileIdOrData) ? $fileIdOrData : [$fileIdOrData];
        $files = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
        $results = [];

        foreach ($fileIds as $index => $fileId) {
            $file = $files[$index] ?? null;
            if (!$file instanceof UploadedFile) continue;

            $uploadUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content";
            $response = $this->guzzle->put($uploadUrl, [
                'body' => fopen($file->getRealPath(), 'rb'),
                'headers' => ['Content-Type' => $file->getMimeType()]
            ]);

            $newName = $targetFileName ?? $file->getClientOriginalName();
            $currentName = $response->json('name');

            if ($newName !== $currentName) {
                $renameUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";
                $this->guzzle->patch($renameUrl, ['json' => ['name' => $newName]]);
            }

            if (!empty($properties)) {
                $this->updateFileProperties($fileId, $properties, $driveId);
            }

            $results[] = [
                'id' => $fileId,
                'name' => $newName,
                'url' => $response->json('webUrl'),
                'ui_url' => $this->generateUiLink($response->json('webUrl')),
            ];
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
        return $this->guzzle->patch($url, ['json' => $properties])->json();
    }

    public function deleteFile(mixed $fileIdOrIds, ?string $driveId = null, bool $ignoreNotFound = true): bool|array
    {
        $driveId = $this->resolveDriveId($driveId);
        $fileIds = is_array($fileIdOrIds) ? $fileIdOrIds : [$fileIdOrIds];
        $results = [];

        foreach ($fileIds as $fileId) {
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";
            try {
                $this->guzzle->delete($url);
                $results[] = true;
            } catch (\Throwable $e) {
                if (!$ignoreNotFound || $e->getCode() != 404) {
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
        $meta = $this->guzzle->get("https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$fileId}")->json();
        $content = $this->getFileContent($fileId, $driveId)['body'];

        return response($content, 200)
            ->header('Content-Type', $meta['file']['mimeType'] ?? 'application/octet-stream')
            ->header('Content-Disposition', "inline; filename=\"{$meta['name']}\"");
    }

    public function getFileContent(string $fileId, ?string $driveId = null): array
    {
        $driveId = $this->resolveDriveId($driveId);
        $meta = $this->guzzle->get("https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$fileId}")->json();
        $response = $this->guzzle->get("https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$fileId}/content");

        return [
            'body' => $response->getBody()->getContents(),
            'mime' => $meta['file']['mimeType'] ?? 'application/octet-stream'
        ];
    }

    /** -----------------------------
     * HELPERS
     * ----------------------------- */
    protected function extractFileInfo($response): array
    {
        $data = is_array($response) ? $response : $response->json();
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'url' => $data['webUrl'] ?? null,
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
