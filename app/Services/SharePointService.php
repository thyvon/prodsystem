<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SharePointService
{
    protected string $accessToken;
    protected string $siteId;
    protected int $chunkSize;
    protected ?User $user;

    public function __construct(User $user, int $chunkSize = 10 * 1024 * 1024)
    {
        $this->siteId = config('services.sharepoint.site_id')
            ?: throw new \InvalidArgumentException('SharePoint site_id not set');

        $this->user = $user;
        $this->chunkSize = $chunkSize;

        // Ensure token is fresh
        $this->accessToken = $this->getValidAccessToken($user);
    }

    /** -----------------------------
     * TOKEN REFRESH
     * ----------------------------- */
    protected function getValidAccessToken(User $user): string
    {
        if (!$user->microsoft_token || !$user->microsoft_refresh_token) {
            throw new \RuntimeException('User does not have Microsoft tokens.');
        }

        // Check expiry, refresh if less than 5 minutes left
        $expiresAt = $user->microsoft_token_expires_at ? Carbon::parse($user->microsoft_token_expires_at) : null;
        if (!$expiresAt || Carbon::now()->greaterThanOrEqualTo($expiresAt->subMinutes(5))) {
            return $this->refreshAccessToken($user);
        }

        return $user->microsoft_token;
    }

    protected function refreshAccessToken(User $user): string
    {
        try {
            $tenantId = config('services.microsoft.tenant_id');
            $clientId = config('services.microsoft.client_id');
            $clientSecret = config('services.microsoft.client_secret');

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $user->microsoft_refresh_token,
                    'scope'         => 'User.Read Files.ReadWrite.All Sites.ReadWrite.All offline_access',
                ]
            );

            if ($response->failed()) {
                Log::error('Microsoft token refresh failed', [
                    'user_id' => $user->id,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
                throw new \RuntimeException('Failed to refresh Microsoft access token.');
            }

            $data = $response->json();

            $user->update([
                'microsoft_token'     => $data['access_token'] ?? $user->microsoft_token,
                'microsoft_refresh_token'    => $data['refresh_token'] ?? $user->microsoft_refresh_token,
                'microsoft_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            return $user->microsoft_token;
        } catch (\Throwable $e) {
            Log::error('Microsoft token refresh exception', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Microsoft access token refresh failed.');
        }
    }

    /** -----------------------------
     * UPLOAD FILES
     * ----------------------------- */

    public function uploadFiles(array $files, string $folderPath, array $properties = [], string $driveId = null): array
    {
        $results = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $results[] = $this->uploadFile($file, $folderPath, $properties, null, $driveId);
            }
        }
        return $results;
    }

    public function uploadFile(
        UploadedFile $file,
        string $folderPath,
        array $properties = [],
        string $fileName = null,
        string $driveId = null
    ): array {
        $fileName = $fileName ?? time() . '_' . $file->getClientOriginalName();
        $driveId = $this->resolveDriveId($driveId);

        $fileInfo = $file->getSize() <= 4 * 1024 * 1024
            ? $this->uploadSmallFile($file, $folderPath, $fileName, $driveId)
            : $this->uploadLargeFile($file, $folderPath, $fileName, $driveId);

        if (!empty($properties)) {
            $this->updateFileProperties($fileInfo['id'], $properties, $driveId);
        }

        $fileInfo['ui_url'] = $this->generateUiLink($fileInfo['url']);
        return $fileInfo;
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
        $response = null;

        while ($offset < $size) {
            $chunk = fread($stream, $this->chunkSize);
            $end = $offset + strlen($chunk) - 1;

            $response = Http::withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$size}"])
                ->put($session, $chunk)
                ->throw();

            $offset += strlen($chunk);
        }

        fclose($stream);
        return $this->extractFileInfo($response);
    }

    /** -----------------------------
     * UPDATE FILES
     * ----------------------------- */

    public function updateFile(string $fileId, UploadedFile $file, array $properties = [], ?string $driveId = null): array
    {
        $driveId = $this->resolveDriveId($driveId);
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content";

        $response = Http::withToken($this->accessToken)
            ->withBody(fopen($file->getRealPath(), 'rb'), $file->getMimeType())
            ->put($url)
            ->throw();

        if (!empty($properties)) {
            $this->updateFileProperties($fileId, $properties, $driveId);
        }

        return array_merge($this->extractFileInfo($response), [
            'ui_url' => $this->generateUiLink($response->json('webUrl'))
        ]);
    }

    public function updateFileProperties(string $fileId, array $properties, ?string $driveId): array
    {
        $driveId = $this->resolveDriveId($driveId);
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/listItem/fields";

        return Http::withToken($this->accessToken)
            ->patch($url, $properties)
            ->throw()
            ->json();
    }

    /** -----------------------------
     * DELETE FILE
     * ----------------------------- */
    public function deleteFile(string $fileId, ?string $driveId = null, bool $ignoreNotFound = true): bool
    {
        $driveId = $this->resolveDriveId($driveId);
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";

        try {
            Http::withToken($this->accessToken)->delete($url)->throw();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if (!$ignoreNotFound || ($e->response && $e->response->status() != 404)) {
                throw $e;
            }
        }

        return true;
    }

    /** -----------------------------
     * FILE CONTENT
     * ----------------------------- */
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
            'mime' => $meta['file']['mimeType'] ?? 'application/octet-stream',
        ];
    }

    public function streamFile(string $fileId, ?string $driveId = null)
    {
        $driveId = $this->resolveDriveId($driveId);
        $meta = Http::withToken($this->accessToken)
            ->get("https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$fileId}")
            ->throw()
            ->json();

        return response($this->getFileContent($fileId, $driveId)['body'], 200)
            ->header('Content-Type', $meta['file']['mimeType'] ?? 'application/octet-stream')
            ->header('Content-Disposition', "inline; filename=\"{$meta['name']}\"");

    }

    /** -----------------------------
     * HELPERS
     * ----------------------------- */

    protected function extractFileInfo($response): array
    {
        return [
            'id'   => $response->json('id'),
            'name' => $response->json('name'),
            'url'  => $response->json('webUrl'),
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
