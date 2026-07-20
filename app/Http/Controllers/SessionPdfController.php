<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Services\SessionPdfService;
use App\Support\SessionPdfSlot;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionPdfController extends Controller
{
    public function __invoke(LegislativeSession $legislativeSession, SessionPdfService $pdfs, string $slot): StreamedResponse
    {
        $this->authorize('view', $legislativeSession);

        abort_unless(SessionPdfSlot::isValid($slot) && SessionPdfSlot::isMirrorable($slot), 404);

        return $pdfs->stream($legislativeSession, $slot);
    }
}
