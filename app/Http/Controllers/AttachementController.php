<?php

namespace App\Http\Controllers;

use App\Models\DocumentRelation;
use Illuminate\Support\Facades\Auth;

class AttachementController extends Controller
{
    public function viewFile(DocumentRelation $file)
    {
        // Optional: authorize access via the parent model
        if (method_exists($file->documentable, 'authorize')) {
            $file->documentable->authorize('view', Auth::user());
        }

        if (!$file->sharepoint_file_id || !$file->sharepoint_drive_id) {
            abort(404, "File not found.");
        }

        $sharePoint = new \App\Services\SharePointService(Auth::user());

        try {
            return $sharePoint->streamFile($file->sharepoint_file_id, $file->sharepoint_drive_id);
        } catch (\Exception $e) {
            abort(404, "File not found or access denied. Error: " . $e->getMessage());
        }
    }
}
