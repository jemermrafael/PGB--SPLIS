<?php

namespace App\Http\Controllers;

use App\Models\Resolution;
use App\Models\ResolutionVersion;
use App\Services\PdfAttachmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResolutionVersionPdfController extends Controller
{
    public function __invoke(
        Resolution $resolution,
        ResolutionVersion $version,
        PdfAttachmentService $pdfs,
    ): StreamedResponse {
        abort_unless($version->resolution_id === $resolution->id, 404);
        $this->authorize('view', $resolution);

        $relative = $version->snapshotValue('pdf_path');
        abort_unless(is_string($relative) && $relative !== '', 404, 'No local file for this version.');

        return $pdfs->stream(
            $resolution->series,
            $resolution->resolution_no,
            $relative,
        );
    }
}
