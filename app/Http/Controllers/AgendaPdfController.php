<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Services\AgendaPdfService;
use App\Support\AgendaPdfSlot;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgendaPdfController extends Controller
{
    public function __invoke(AgendaItem $agenda, AgendaPdfService $pdfs, string $slot): StreamedResponse
    {
        $this->authorize('view', $agenda);

        abort_unless(AgendaPdfSlot::isValid($slot), 404);

        return $pdfs->stream($agenda, $slot);
    }
}
