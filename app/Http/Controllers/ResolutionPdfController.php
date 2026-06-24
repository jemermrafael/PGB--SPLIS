<?php

namespace App\Http\Controllers;

use App\Models\Resolution;
use App\Services\PdfAttachmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResolutionPdfController extends Controller
{
    public function __invoke(int $series, string $resolutionNo, PdfAttachmentService $pdf): StreamedResponse
    {
        $decoded = urldecode($resolutionNo);
        $pdfPath = Resolution::query()
            ->where('series', $series)
            ->where('resolution_no', $decoded)
            ->value('pdf_path');

        return $pdf->stream($series, $decoded, $pdfPath);
    }
}
