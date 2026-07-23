<?php

namespace App\Http\Controllers;

use App\Models\Ordinance;
use App\Models\OrdinanceVersion;
use App\Services\OrdinancePdfService;
use App\Support\OrdinancePdfType;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdinanceVersionPdfController extends Controller
{
    public function __invoke(
        Ordinance $ordinance,
        OrdinanceVersion $version,
        string $type,
        OrdinancePdfService $pdfs,
    ): StreamedResponse {
        abort_unless($version->ordinance_id === $ordinance->id, 404);
        $this->authorize('view', $ordinance);
        abort_unless(OrdinancePdfType::isValid($type), 404);

        $pathColumn = OrdinancePdfType::config($type)['path'];
        $relative = $version->snapshotValue($pathColumn);

        abort_unless(is_string($relative) && $relative !== '', 404, 'No local file for this version.');

        return $pdfs->streamRelative($relative);
    }
}
