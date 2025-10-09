<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;

class SharePointService
{
    protected string $accessToken;
    protected string $siteId;
    protected int $chunkSize;

    public function __construct(string $accessToken, int $chunkSize = 10 * 1024 * 1024)
    {
        $this->siteId = config('services.sharepoint.site_id')
            ?: throw new \InvalidArgumentException('SharePoint site_id not set');
        $this->accessToken = $accessToken;
        $this->chunkSize = $chunkSize;
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

        // Small vs large file
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

    public function updateFiles(array $files, array $fileIds, array $properties = [], string $driveId = null): array
    {
        $results = [];
        foreach ($files as $i => $file) {
            if ($file instanceof UploadedFile && isset($fileIds[$i])) {
                $results[] = $this->updateFile($fileIds[$i], $file, $properties, $driveId);
            }
        }
        return $results;
    }

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
        $driveId = $driveId ?? $this->getDefaultDriveId();
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$driveId}/items/{$fileId}";

        try {
            Http::withToken($this->accessToken)->delete($url)->throw();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Ignore 404 errors if file does not exist
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
