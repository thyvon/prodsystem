<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;

class SharePointService
{
    protected string $accessToken;
    protected string $siteId;
    protected string $driveId;
    protected int $chunkSize; // default 10MB

    public function __construct(string $accessToken, string $driveId = null, int $chunkSize = 10 * 1024 * 1024)
    {
        $this->siteId = config('services.sharepoint.site_id') 
            ?: throw new \InvalidArgumentException('SharePoint site_id not set');
        $this->driveId = $driveId ?? config('services.sharepoint.drive_id') 
            ?: throw new \InvalidArgumentException('SharePoint drive_id not set');
        $this->accessToken = $accessToken;
        $this->chunkSize = $chunkSize;
    }

    public function uploadFile(UploadedFile $file, string $folderPath, array $properties = [], string $fileName = null): array
    {
        $fileName = $fileName ?? time() . '_' . $file->getClientOriginalName();

        $fileInfo = $file->getSize() <= 4 * 1024 * 1024
            ? $this->uploadSmallFile($file, $folderPath, $fileName)
            : $this->uploadLargeFile($file, $folderPath, $fileName);

        if (!empty($properties)) {
            $this->updateFileProperties($fileInfo['id'], $properties);
        }

        return $fileInfo;
    }

    protected function uploadSmallFile(UploadedFile $file, string $folderPath, string $fileName): array
    {
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$this->driveId}/root:/{$folderPath}/{$fileName}:/content";

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

    protected function uploadLargeFile(UploadedFile $file, string $folderPath, string $fileName): array
    {
        $uploadUrl = Http::withToken($this->accessToken)
            ->post("https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$this->driveId}/root:/{$folderPath}/{$fileName}:/createUploadSession", [
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
            ])->put($uploadUrl, $chunk)
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

    protected function updateFileProperties(string $fileId, array $properties): array
    {
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$this->driveId}/items/{$fileId}/listItem/fields";

        return Http::withToken($this->accessToken)
            ->patch($url, $properties)
            ->throw()
            ->json();
    }

    /**
     * Delete a file by its ID
     */
    public function deleteFile(string $fileId): bool
    {
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$this->driveId}/items/{$fileId}";

        Http::withToken($this->accessToken)
            ->delete($url)
            ->throw();

        return true;
    }

    /**
     * Update an existing file (replace content)
     */
    public function updateFile(string $fileId, UploadedFile $file, array $properties = []): array
    {
        // Update content
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$this->driveId}/items/{$fileId}/content";

        $response = Http::withToken($this->accessToken)
            ->withBody(fopen($file->getRealPath(), 'rb'), $file->getMimeType())
            ->put($url)
            ->throw();

        // Update properties if provided
        if (!empty($properties)) {
            $this->updateFileProperties($fileId, $properties);
        }

        return [
            'id' => $response->json('id'),
            'name' => $response->json('name'),
            'url' => $response->json('webUrl'),
        ];
    }
}
