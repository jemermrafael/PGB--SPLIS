<?php

namespace App\Http\Controllers;

use App\Models\AppropriationOrdinance;
use App\Services\AppropriationOrdinancePdfService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppropriationOrdinancePdfController extends Controller
{
    public function __invoke(AppropriationOrdinance $appropriationOrdinance, AppropriationOrdinancePdfService $pdfs): StreamedResponse
    {
        $this->authorize('view', $appropriationOrdinance);

        return $pdfs->stream($appropriationOrdinance);
    }
}
