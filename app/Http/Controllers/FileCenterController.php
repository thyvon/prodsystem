<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FileServerService;
use Illuminate\Support\Facades\Log;

class FileCenterController extends Controller
{
        protected FileServerService $fileServerService;

    public function __construct(FileServerService $fileServerService)
    {
        $this->fileServerService = $fileServerService;
    }

    /**
     * List files in a folder
     */
    public function listFolder(Request $request)
    {
        $folder = $request->query('folder', '');
        $data = $this->fileServerService->listFolder($folder);

        return response()->json([
            'success' => true,
            'folders' => $data['folders'],
            'files' => $data['files'],
            'currentFolder' => $folder,
        ]);
    }

    public function index()
    {
        return view('file-center.index');
    }

    public function viewFileByPath($path)
    {
        if (!$path) {
            abort(404, "File not specified.");
        }

        try {
            // Stream the file using your service
            return $this->fileServerService->streamFile($path);
        } catch (\Throwable $e) {
            Log::error("File stream failed for {$path}: {$e->getMessage()}");
            abort(404, "File not found or access denied.");
        }
    }

}

