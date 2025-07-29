<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function show()
    {
        return view('pdf.pdfviewer');
    }

    public function servePdf($filename)
    {
        $path = public_path('documents/' . $filename);
        
        if (!file_exists($path)) {
            abort(404, 'PDF not found');
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}