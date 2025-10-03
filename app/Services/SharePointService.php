<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;

class SharePointService
{
    protected string $accessToken;
    protected string $siteId;
    protected string $driveId;

    /**
     * @param string $accessToken User delegated token
     * @param string|null $driveId Optional: override default drive/library ID
     */
    public function __construct(string $accessToken, string $driveId = null)
    {
        $this->accessToken = $accessToken;
        $this->siteId = config('services.sharepoint.site_id');
        $this->driveId = $driveId ?? config('services.sharepoint.drive_id');
    }

    public function uploadFile(UploadedFile $file, string $folderPath, array $properties = []): array
    {
        $fileName = time() . '_' . $file->getClientOriginalName();
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$this->driveId}/root:/{$folderPath}/{$fileName}:/content";

        $response = Http::withToken($this->accessToken)
            ->attach('file', file_get_contents($file->getRealPath()), $fileName)
            ->put($url);

        if ($response->failed()) {
            throw new \Exception('Failed to upload file to SharePoint: ' . $response->body());
        }

        $fileInfo = $response->json();

        if (!empty($properties)) {
            $this->updateFileProperties($fileInfo['id'], $properties);
        }

        return [
            'id' => $fileInfo['id'],
            'name' => $fileInfo['name'],
            'url' => $fileInfo['webUrl'],
        ];
    }

    protected function updateFileProperties(string $fileId, array $properties)
    {
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$this->driveId}/items/{$fileId}/listItem/fields";

        $response = Http::withToken($this->accessToken)->patch($url, $properties);

        if ($response->failed()) {
            throw new \Exception('Failed to update SharePoint file properties: ' . $response->body());
        }

        return $response->json();
    }
}
