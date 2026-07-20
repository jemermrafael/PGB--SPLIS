<?php

namespace App\Http\Controllers;

use App\Models\Ordinance;
use App\Services\OrdinancePdfService;
use App\Support\OrdinancePdfType;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdinancePdfController extends Controller
{
    public function __invoke(Ordinance $ordinance, OrdinancePdfService $pdfs, ?string $type = null): StreamedResponse
    {
        $this->authorize('view', $ordinance);

        $type = $type ?? OrdinancePdfType::MAIN;
        abort_unless(OrdinancePdfType::isValid($type), 404);

        return $pdfs->stream($ordinance, $type);
    }
}
