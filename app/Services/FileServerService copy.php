<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class FileServerService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected Client $guzzle;

    /**
     * Constructor
     *
     * @param string $baseUrl HFS folder URL, e.g., https://file.mjqe-purchasing.site/File-Storage
     * @param string $username HFS user
     * @param string $password HFS password
     */
    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.file_server.base_url'), '/');
        $this->username = config('services.file_server.username');
        $this->password = config('services.file_server.password');
        $this->guzzle = new \GuzzleHttp\Client(['timeout' => 0, 'connect_timeout' => 0]);
    }

    /**
     * Auth options for Guzzle
     */
    protected function authOptions(): array
    {
        return ['auth' => [$this->username, $this->password]];
    }

    /**
     * Upload file to server
     *
     * @param UploadedFile $file
     * @param string $folderPath relative folder path
     * @param string|null $remoteName optional custom filename
     * @return array
     */
    public function uploadFile(UploadedFile $file, string $folderPath = '', ?string $remoteName = null): array
    {
        $remoteName = $remoteName ?? $file->getClientOriginalName();
        $url = $this->baseUrl . '/' . trim($folderPath, '/') . '/' . $remoteName;

        try {
            $this->guzzle->put($url, array_merge($this->authOptions(), [
                'body' => fopen($file->getRealPath(), 'rb'),
                'headers' => ['Content-Type' => $file->getMimeType()],
            ]));

            return [
                'name' => $remoteName,
                'path' => trim($folderPath, '/') . '/' . $remoteName,
                'url' => $url,
            ];
        } catch (\Throwable $e) {
            Log::error("File upload failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Delete file from server
     *
     * @param string $filePath relative file path on server
     * @return bool
     */
    public function deleteFile(string $filePath): bool
    {
        $url = $this->baseUrl . '/' . ltrim($filePath, '/');

        try {
            $this->guzzle->delete($url, $this->authOptions());
            return true;
        } catch (\Throwable $e) {
            Log::warning("File delete failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Stream file to browser
     *
     * @param string $filePath relative file path on server
     */
    public function streamFile(string $filePath)
    {
        // Ensure clean and valid URL
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($filePath, '/');

        try {
            // Send GET request with authentication
            $response = $this->guzzle->get($url, [
                'auth' => [$this->username, $this->password],
                'http_errors' => false,
                'stream' => true,
            ]);

            // Check for valid response
            if ($response->getStatusCode() !== 200) {
                throw new \Exception("File server returned status: " . $response->getStatusCode());
            }

            // Get MIME type and file content
            $mimeType = $response->getHeaderLine('Content-Type') ?: 'application/octet-stream';
            $content = $response->getBody()->getContents();
            $fileName = basename($filePath);

            // Return streamed response
            return response($content, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');

        } catch (\Throwable $e) {
            Log::error("File stream failed for {$filePath}: {$e->getMessage()}");
            abort(404, 'File not found or cannot be accessed.');
        }
    }


    /**
     * List files in folder
     *
     * @param string $folderPath relative folder path
     * @return array
     */
    public function listFiles(string $folderPath = ''): array
    {
        $url = $this->baseUrl . '/' . trim($folderPath, '/');

        try {
            $response = $this->guzzle->get($url, $this->authOptions());
            $body = $response->getBody()->getContents();

            // Extract links from HFS folder page
            preg_match_all('/<a href="([^"]+)"/i', $body, $matches);
            return $matches[1] ?? [];
        } catch (\Throwable $e) {
            Log::warning("List files failed: {$e->getMessage()}");
            return [];
        }
    }
}
