<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileServerService
{
    protected string $disk;

    public function __construct()
    {
        // Use the Wasabi disk you configured in config/filesystems.php
        $this->disk = 'wasabi';
    }

    /**
     * Upload file to Wasabi
     *
     * @param UploadedFile $file
     * @param string $folderPath
     * @param string|null $remoteName
     * @return array
     */
    public function uploadFile(UploadedFile $file, string $folderPath = '', ?string $remoteName = null): array
    {
        $remoteName = $remoteName ?? $file->getClientOriginalName();
        $path = trim($folderPath, '/') . '/' . $remoteName;

        try {
            // Store the file
            Storage::disk($this->disk)->putFileAs(trim($folderPath, '/'), $file, $remoteName, 'public');

            // $url = Storage::disk($this->disk)->temporaryUrl($path, now()->addMinutes(10));

            return [
                'name' => $remoteName,
                'path' => $path,
                // 'url' => $url,
            ];
        } catch (\Throwable $e) {
            Log::error("Wasabi upload failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Delete file from Wasabi
     *
     * @param string $filePath
     * @return bool
     */
    // public function deleteFile(string $filePath): bool
    // {
    //     try {
    //         return Storage::disk($this->disk)->delete($filePath);
    //     } catch (\Throwable $e) {
    //         Log::warning("Wasabi delete failed: {$e->getMessage()}");
    //         return false;
    //     }
    // }

    public function deleteFile(string $filePath): bool
    {
        try {
            if (!Storage::disk($this->disk)->exists($filePath)) {
                Log::warning('Wasabi file does not exist', [
                    'path' => $filePath,
                ]);
                return true;
            }

            $deleted = Storage::disk($this->disk)->delete($filePath);

            return true;
        } catch (\Throwable $e) {
            Log::error('Wasabi delete exception', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * Stream file to browser
     *
     * @param string $filePath
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function streamFile(string $filePath)
    {
        try {
            if (!Storage::disk($this->disk)->exists($filePath)) {
                abort(404, 'File not found.');
            }

            $mimeType = Storage::disk($this->disk)->mimeType($filePath);
            $stream = Storage::disk($this->disk)->readStream($filePath);
            $fileName = basename($filePath);

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error("Wasabi stream failed: {$e->getMessage()}");
            abort(404, 'File cannot be accessed.');
        }
    }

    /**
     * List files in a folder
     *
     * @param string $folderPath
     * @return array
     */
    // public function listFiles(string $folderPath = ''): array
    // {
    //     try {
    //         return Storage::disk($this->disk)->files(trim($folderPath, '/'));
    //     } catch (\Throwable $e) {
    //         Log::warning("Wasabi list files failed: {$e->getMessage()}");
    //         return [];
    //     }
    // }

}
