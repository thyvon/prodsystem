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
     * Upload single or multiple files
     */
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

    /**
     * Upload single file
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
     * Update single or multiple files
     */
    public function updateFiles(array $files, array $fileIds, array $properties = [], string $driveId = null): array
    {
        $results = [];
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile && isset($fileIds[$index])) {
                $results[] = $this->updateFile($fileIds[$index], $file, $properties, $driveId);
            }
        }
        return $results;
    }

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

    public function updateFileProperties(string $fileId, array $properties, ?string $driveId): array
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/listItem/fields";

        return Http::withToken($this->accessToken)
            ->patch($url, $properties)
            ->throw()
            ->json();
    }

    public function deleteFile(string $fileId, ?string $driveId = null): bool
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";

        Http::withToken($this->accessToken)
            ->delete($url)
            ->throw();

        return true;
    }

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

    public function getFileContent(string $fileId, ?string $driveId = null): array
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $metaUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";
        $meta = Http::withToken($this->accessToken)->get($metaUrl)->throw()->json();
        $mime = $meta['file']['mimeType'] ?? 'application/octet-stream';

        $contentUrl = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}/content";
        $response = Http::withToken($this->accessToken)->get($contentUrl)->throw();

        return [
            'body' => $response->body(),
            'mime' => $mime,
        ];
    }

    public function streamFile(string $fileId, ?string $driveId = null)
    {
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $meta = Http::withToken($this->accessToken)
            ->get("https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$fileId}")
            ->json();

        if (empty($meta['id'])) {
            throw new \Exception("File metadata not found");
        }

        $fileName = $meta['name'] ?? $fileId;
        $contentType = $meta['file']['mimeType'] ?? 'application/octet-stream';
        $url = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$fileId}/content";
        $response = Http::withToken($this->accessToken)->get($url);

        if ($response->failed()) {
            throw new \Exception("Failed to fetch file from SharePoint");
        }

        return response($response->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', "inline; filename=\"{$fileName}\"");
    }

    protected function generateUiLink(string $webUrl): string
    {
        $siteRelativePath = str_replace('https://mjqeducationplc.sharepoint.com', '', $webUrl);
        $siteRelativePath = rawurldecode($siteRelativePath);

        $fileName = basename($siteRelativePath);
        $folderPath = dirname($siteRelativePath);

        $encodedFile = rawurlencode($siteRelativePath);
        $encodedParent = rawurlencode($folderPath);

        $segments = explode('/', trim($siteRelativePath, '/'));
        $libraryName = $segments[2] ?? $segments[1] ?? $segments[0];

        return "https://mjqeducationplc.sharepoint.com/sites/PRODMJQE/{$libraryName}/Forms/AllItems.aspx?id={$encodedFile}&parent={$encodedParent}&p=true&ga=1";
    }

    protected function getDefaultDriveId(): string
    {
        return config('services.sharepoint.drive_id')
            ?: throw new \InvalidArgumentException('SharePoint default drive_id not set');
    }
}
