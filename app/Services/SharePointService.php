<?php

namespace App\Services;

use GuzzleHttp\Client;
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
    protected Client $guzzle;

    public function __construct(User $user, int $chunkSize = 50 * 1024 * 1024) // 50MB
    {
        $this->user = $user;
        $this->chunkSize = $chunkSize;
        $this->siteId = config('services.sharepoint.site_id')
            ?: throw new \InvalidArgumentException('SharePoint site_id not set');
        $this->guzzle = new Client(['timeout' => 0, 'connect_timeout' => 0]);
    }

    /** -----------------------------
     * TOKEN MANAGEMENT
     * ----------------------------- */

    protected function getValidAccessToken(): string
    {
        // If tokens are missing, force login
        if (!$this->user->microsoft_token || !$this->user->microsoft_refresh_token) {
            Log::warning('Missing tokens, forcing re-auth', ['user_id' => $this->user->id]);
            return $this->forceMicrosoftLogin();
        }

        $expiresAt = $this->user->microsoft_token_expires_at
            ? Carbon::parse($this->user->microsoft_token_expires_at)
            : null;

        // Refresh if token expired or less than 15 minutes left (extended buffer)
        if (!$expiresAt || Carbon::now()->greaterThanOrEqualTo($expiresAt->subMinutes(15))) {
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

            $response = $this->guzzle->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'form_params' => [
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $this->user->microsoft_refresh_token,
                        'scope' => 'User.Read Files.ReadWrite.All Sites.ReadWrite.All offline_access',
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            // If refresh fails, force login
            if (!isset($data['access_token']) || isset($data['error'])) {
                Log::warning('Microsoft token refresh failed', [
                    'user_id' => $this->user->id,
                    'body' => $data,
                ]);
                return $this->forceMicrosoftLogin();
            }

            // Save new tokens
            $this->user->update([
                'microsoft_token' => $data['access_token'],
                'microsoft_refresh_token' => $data['refresh_token'] ?? $this->user->microsoft_refresh_token,
                'microsoft_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            Log::info("✅ Microsoft token refreshed for user {$this->user->id}");

            return $data['access_token'];
        } catch (\Throwable $e) {
            Log::error('Microsoft token refresh exception', [
                'user_id' => $this->user->id ?? null,
                'message' => $e->getMessage(),
            ]);

            // Force login if refresh fails
            return $this->forceMicrosoftLogin();
        }
    }

    protected function forceMicrosoftLogin(): never
    {
        // Clear tokens
        $this->user->update([
            'microsoft_token' => null,
            'microsoft_refresh_token' => null,
            'microsoft_token_expires_at' => null,
        ]);

        // Redirect user to Microsoft login route
        redirect()->route('microsoft.login')->send();
        exit;
    }

    /** -----------------------------
     * FILE UPLOAD
     * ----------------------------- */

    public function uploadFile(mixed $fileOrFiles, string $folderPath, array $properties = [], ?string $fileName = null, ?string $driveId = null): array
    {
        $this->accessToken = $this->getValidAccessToken();
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

        $response = $this->guzzle->put($url, [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => $file->getMimeType(),
            ],
            'body' => fopen($file->getRealPath(), 'rb'),
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'url' => $data['webUrl'] ?? null,
        ];
    }

    protected function uploadLargeFile(UploadedFile $file, string $folderPath, string $fileName, string $driveId): array
    {
        $sessionResponse = $this->guzzle->post(
            "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/root:/{$folderPath}/{$fileName}:/createUploadSession",
            [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
                'json' => ['item' => ['@microsoft.graph.conflictBehavior' => 'replace']]
            ]
        );

        $uploadUrl = json_decode($sessionResponse->getBody()->getContents(), true)['uploadUrl'];

        $stream = fopen($file->getRealPath(), 'rb');
        $size = $file->getSize();
        $offset = 0;

        while ($offset < $size) {
            $chunk = fread($stream, $this->chunkSize);
            $end = $offset + strlen($chunk) - 1;

            $this->guzzle->put($uploadUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Range' => "bytes {$offset}-{$end}/{$size}",
                ],
                'body' => $chunk,
            ]);

            $offset += strlen($chunk);
        }

        fclose($stream);

        return [
            'id' => basename($fileName),
            'name' => $fileName,
            'url' => $uploadUrl
        ];
    }

    /** -----------------------------
     * FILE UPDATE
     * ----------------------------- */

    public function updateFile(string|array $fileIdOrData, mixed $fileOrFiles, array $properties = [], ?string $driveId = null, ?string $targetFileName = null): array
    {
        $this->accessToken = $this->getValidAccessToken();
        $driveId = $this->resolveDriveId($driveId);

        $fileIds = is_array($fileIdOrData) ? $fileIdOrData : [$fileIdOrData];
        $files = is_array($fileOrFiles) ? $fileOrFiles : [$fileOrFiles];
        $results = [];

        foreach ($fileIds as $index => $fileId) {
            $file = $files[$index] ?? null;
            if (!$file instanceof UploadedFile) continue;

            $newName = $targetFileName ?? $file->getClientOriginalName();

            // Replace content
            $uploadUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content";
            $this->guzzle->put($uploadUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => $file->getMimeType(),
                ],
                'body' => fopen($file->getRealPath(), 'rb'),
            ]);

            // Rename file
            $renameUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";
            $this->guzzle->patch($renameUrl, [
                'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
                'json' => ['name' => $newName],
            ]);

            // Update metadata
            if (!empty($properties)) {
                $this->updateFileProperties($fileId, $properties, $driveId);
            }

            // Get file info
            $infoUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";
            $response = $this->guzzle->get($infoUrl, ['headers' => ['Authorization' => "Bearer {$this->accessToken}"]]);
            $data = json_decode($response->getBody()->getContents(), true);

            $results[] = [
                'id' => $fileId,
                'name' => $newName,
                'url' => $data['webUrl'] ?? null,
                'ui_url' => $this->generateUiLink($data['webUrl'] ?? ''),
            ];
        }

        return is_array($fileOrFiles) ? $results : $results[0];
    }

    /** -----------------------------
     * FILE PROPERTIES / DELETE / STREAM
     * ----------------------------- */

    public function updateFileProperties(string $fileId, array $properties, ?string $driveId = null): array
    {
        $this->accessToken = $this->getValidAccessToken();
        $driveId = $this->resolveDriveId($driveId);
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/listItem/fields";

        $response = $this->guzzle->patch($url, [
            'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
            'json' => $properties
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function deleteFile(mixed $fileIdOrIds, ?string $driveId = null, bool $ignoreNotFound = true): bool|array
    {
        $this->accessToken = $this->getValidAccessToken();
        $driveId = $this->resolveDriveId($driveId);
        $fileIds = is_array($fileIdOrIds) ? $fileIdOrIds : [$fileIdOrIds];
        $results = [];

        foreach ($fileIds as $fileId) {
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";
            try {
                $this->guzzle->delete($url, ['headers' => ['Authorization' => "Bearer {$this->accessToken}"]]);
                $results[] = true;
            } catch (\Throwable $e) {
                if (!$ignoreNotFound) throw $e;
                $results[] = false;
            }
        }

        return is_array($fileIdOrIds) ? $results : $results[0];
    }

    public function streamFile(string $fileId, ?string $driveId = null)
    {
        $this->accessToken = $this->getValidAccessToken();
        $driveId = $this->resolveDriveId($driveId);
        $meta = $this->getFileMetadata($fileId, $driveId);
        $content = $this->getFileContent($fileId, $driveId)['body'];

        // Determine MIME type
        $mimeType = $meta['file']['mimeType'] ?? 'application/octet-stream';
        if (str_ends_with(strtolower($meta['name']), '.pdf')) {
            $mimeType = 'application/pdf'; // Force PDF.js compatible type
        }

        return response($content, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', "inline; filename=\"{$meta['name']}\"")
            ->header('Accept-Ranges', 'bytes'); // optional, helps PDF.js with large PDFs
    }

    public function getFileContent(string $fileId, ?string $driveId = null): array
    {
        $this->accessToken = $this->getValidAccessToken();
        $driveId = $this->resolveDriveId($driveId);
        $response = $this->guzzle->get(
            "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content",
            ['headers' => ['Authorization' => "Bearer {$this->accessToken}"]]
        );

        $meta = $this->getFileMetadata($fileId, $driveId);

        return [
            'body' => $response->getBody()->getContents(),
            'mime' => $meta['file']['mimeType'] ?? 'application/octet-stream'
        ];
    }

    protected function getFileMetadata(string $fileId, string $driveId): array
    {
        $response = $this->guzzle->get(
            "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$fileId}",
            ['headers' => ['Authorization' => "Bearer {$this->accessToken}"]]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /** -----------------------------
     * HELPERS
     * ----------------------------- */

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