<?php

namespace App\Http\Controllers;

use App\Models\DocumentRelation;
use App\Services\FileServerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AttachementController extends Controller
{
    protected FileServerService $fileServerService;

    /**
     * Inject FileServerService via constructor
     */
    public function __construct(FileServerService $fileServerService)
    {
        $this->fileServerService = $fileServerService;
    }

    /**
     * View or stream a file from the File Server
     */
    public function viewFile(DocumentRelation $file)
    {
        // Optional: authorize access via parent model
        if (method_exists($file->documentable, 'authorize')) {
            $file->documentable->authorize('view', Auth::user());
        }

        // Validate file path
        if (!$file->path) {
            abort(404, "File not found.");
        }

        try {
            // Stream the file using FileServerService
            return $this->fileServerService->streamFile($file->path);
        } catch (\Throwable $e) {
            Log::error("File stream failed for {$file->path}: {$e->getMessage()}");
            abort(404, "File not found or access denied.");
        }
    }
}
