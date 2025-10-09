<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;

class SharePointService
{
    protected string $accessToken;
    protected string $siteId;
    protected int $chunkSize; // default 10MB

    public function __construct(string $accessToken, int $chunkSize = 10 * 1024 * 1024)
    {
        $this->siteId = config('services.sharepoint.site_id')
            ?: throw new \InvalidArgumentException('SharePoint site_id not set');
        $this->accessToken = $accessToken;
        $this->chunkSize = $chunkSize;
    }

    /**
     * Upload file to SharePoint
     */
    public function uploadFile(
        UploadedFile $file,
        string $folderPath,
        array $properties = [],
        string $fileName = null,
        string $driveId = null
    ): array {
        $fileName = $fileName ?? time() . '_' . $file->getClientOriginalName();

        $fileInfo = $file->getSize() <= 4 * 1024 * 1024
            ? $this->uploadSmallFile($file, $folderPath, $fileName, $driveId)
            : $this->uploadLargeFile($file, $folderPath, $fileName, $driveId);

        if (!empty($properties)) {
            $this->updateFileProperties($fileInfo['id'], $properties, $driveId);
        }

        $fileInfo['ui_url'] = $this->generateUiLink($fileInfo['url']);

        return $fileInfo;
    }

    /**
     * Upload small file (<= 4MB)
     */
    protected function uploadSmallFile(UploadedFile $file, string $folderPath, string $fileName, ?string $driveId): array
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/root:/{$folderPath}/{$fileName}:/content";

        $response = Http::withToken($this->accessToken)
            ->withBody(fopen($file->getRealPath(), 'rb'), $file->getMimeType())
            ->put($url)
            ->throw();

        return [
            'id' => $response->json('id'),
            'name' => $response->json('name'),
            'url' => $response->json('webUrl'),
        ];
    }

    /**
     * Upload large file (> 4MB) in chunks
     */
    protected function uploadLargeFile(UploadedFile $file, string $folderPath, string $fileName, ?string $driveId): array
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
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

            $response = Http::withHeaders([
                'Content-Range' => "bytes {$offset}-{$end}/{$size}"
            ])->put($session, $chunk)
              ->throw();

            $offset += strlen($chunk);
        }

        fclose($stream);

        return [
            'id' => $response->json('id'),
            'name' => $response->json('name'),
            'url' => $response->json('webUrl'),
        ];
    }

    /**
     * Update file properties
     */
    public function updateFileProperties(string $fileId, array $properties, ?string $driveId): array
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/listItem/fields";

        return Http::withToken($this->accessToken)
            ->patch($url, $properties)
            ->throw()
            ->json();
    }

    /**
     * Delete a file by ID
     */
    public function deleteFile(string $fileId, ?string $driveId = null): bool
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";

        Http::withToken($this->accessToken)
            ->delete($url)
            ->throw();

        return true;
    }

    /**
     * Update existing file content
     */
    public function updateFile(string $fileId, UploadedFile $file, array $properties = [], ?string $driveId = null): array
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content";

        $response = Http::withToken($this->accessToken)
            ->withBody(fopen($file->getRealPath(), 'rb'), $file->getMimeType())
            ->put($url)
            ->throw();

        if (!empty($properties)) {
            $this->updateFileProperties($fileId, $properties, $driveId);
        }

        return [
            'id' => $response->json('id'),
            'name' => $response->json('name'),
            'url' => $response->json('webUrl'),
            'ui_url' => $this->generateUiLink($response->json('webUrl')),
        ];
    }

    /**
     * Generate SharePoint modern UI link dynamically
     */
    protected function generateUiLink(string $webUrl): string
    {
        $siteRelativePath = str_replace('https://mjqeducationplc.sharepoint.com', '', $webUrl);
        $siteRelativePath = rawurldecode($siteRelativePath);

        $fileName = basename($siteRelativePath);
        $folderPath = dirname($siteRelativePath);

        $encodedFile = rawurlencode($siteRelativePath);
        $encodedParent = rawurlencode($folderPath);

        $segments = explode('/', trim($siteRelativePath, '/'));
        if (isset($segments[2])) {
            $libraryName = $segments[2];
        } else {
            $libraryName = $segments[1] ?? $segments[0];
        }

        return "https://mjqeducationplc.sharepoint.com/sites/PRODMJQE/{$libraryName}/Forms/AllItems.aspx?id={$encodedFile}&parent={$encodedParent}&p=true&ga=1";
    }

    /**
     * Get default drive ID from config
     */
    protected function getDefaultDriveId(): string
    {
        return config('services.sharepoint.drive_id') 
            ?: throw new \InvalidArgumentException('SharePoint default drive_id not set');
    }

    /**
     * Get Drive ID by library name dynamically
     */
    public function getDriveIdByName(string $libraryName): string
    {
        $response = Http::withToken($this->accessToken)
            ->get("https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives")
            ->throw()
            ->json('value');

        foreach ($response as $drive) {
            if ($drive['name'] === $libraryName) {
                return $drive['id'];
            }
        }

        throw new \Exception("Drive not found: {$libraryName}");
    }

    /**
     * Get file content for inline viewing
     */
    public function getFileContent(string $fileId, ?string $driveId = null): array
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();

        // Get file metadata
        $metaUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";
        $meta = Http::withToken($this->accessToken)
            ->get($metaUrl)
            ->throw()
            ->json();

        $mime = $meta['file']['mimeType'] ?? 'application/octet-stream';

        // Download file content
        $contentUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content";

        $response = Http::withToken($this->accessToken)
            ->get($contentUrl)
            ->throw();

        return [
            'body' => $response->body(),
            'mime' => $mime,
        ];
    }

    /**
 * Stream a file from SharePoint inline (view in browser)
 */
    public function viewFile(string $fileId, ?string $driveId = null)
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();

        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content";

        $response = Http::withToken($this->accessToken)
            ->withOptions(['stream' => true])
            ->get($url);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch file from SharePoint');
        }

        // Determine content type
        $contentType = $response->header('Content-Type') ?? 'application/octet-stream';
        $fileName = $fileId; // fallback filename

        // Try to get real filename from Graph metadata
        $meta = Http::withToken($this->accessToken)
            ->get("https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}")
            ->json();

        if (!empty($meta['name'])) {
            $fileName = $meta['name'];
        }

        // Stream file inline
        return response()->stream(function () use ($response) {
            echo $response->body();
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "inline; filename=\"{$fileName}\"",
        ]);
    }

}
