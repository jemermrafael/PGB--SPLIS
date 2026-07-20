<?php

namespace App\Http\Controllers;

use App\Models\Ordinance;
use App\Services\OrdinancePdfService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdinancePdfController extends Controller
{
    public function __invoke(Ordinance $ordinance, OrdinancePdfService $pdfs): StreamedResponse
    {
        $this->authorize('view', $ordinance);

        return $pdfs->stream($ordinance);
    }
}
