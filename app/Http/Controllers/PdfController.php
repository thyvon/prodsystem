<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class PdfController extends Controller
{
    public function generate()
    {
        $invoiceData = [
            'number' => 'INV-' . now()->format('YmdHis'),
            'date' => now()->format('Y-m-d'),
            'customer' => 'Mr. Vun Thy',
            'amount' => 1250.00,
        ];

        $pdf = Pdf::loadView('pdf.pdf', ['invoice' => $invoiceData]);

        $filename = Str::slug($invoiceData['number']) . '.pdf';
        $path = public_path("pdf/invoice/{$filename}");

        // Ensure the folder exists
        if (!File::exists(public_path('pdf/invoice'))) {
            File::makeDirectory(public_path('pdf/invoice'), 0755, true);
        }

        $pdf->save($path);

        // Redirect to PDF.js viewer
        return redirect("/pdf-viewer/{$filename}");
    }
}