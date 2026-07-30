<?php

namespace App\Http\Controllers;

use App\Models\AppropriationOrdinance;
use App\Models\AppropriationOrdinanceVersion;
use App\Services\AppropriationOrdinancePdfService;
use App\Support\MediaType;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppropriationOrdinanceVersionPdfController extends Controller
{
    public function __invoke(
        AppropriationOrdinance $appropriationOrdinance,
        AppropriationOrdinanceVersion $version,
        AppropriationOrdinancePdfService $pdfs,
    ): StreamedResponse {
        abort_unless($version->appropriation_ordinance_id === $appropriationOrdinance->id, 404);
        $this->authorize('view', $appropriationOrdinance);

        $relative = $version->snapshotValue('pdf_path');
        abort_unless(is_string($relative) && $relative !== '', 404, 'No local file for this version.');

        $path = $pdfs->absolutePath($relative);
        abort_if($path === null, 404, 'File not found.');

        $media = MediaType::fromPath($path);
        abort_if($media === null, 404, 'Unsupported file type.');

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
