<?php

namespace App\Http\Controllers;

use App\Models\Resolution;
use App\Services\PdfAttachmentService;
use App\Support\MunicipalRequestAccess;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResolutionPdfController extends Controller
{
    public function __invoke(int $series, string $resolutionNo, PdfAttachmentService $pdf): StreamedResponse
    {
        $decoded = urldecode($resolutionNo);
        $resolution = Resolution::query()
            ->where('series', $series)
            ->where('resolution_no', $decoded)
            ->firstOrFail();

        $user = auth()->user();
        abort_unless($user && MunicipalRequestAccess::userCanViewResolution($user, $resolution), 403);

        return $pdf->stream($series, $decoded, $resolution->pdf_path);
    }
}
