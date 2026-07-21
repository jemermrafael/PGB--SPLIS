<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Services\SessionPdfService;
use App\Support\SessionPdfSlot;
use Illuminate\Http\RedirectResponse;

class SessionPdfDeleteController extends Controller
{
    public function __invoke(
        LegislativeSession $legislativeSession,
        string $slot,
        SessionPdfService $pdfs,
    ): RedirectResponse {
        $this->authorize('update', $legislativeSession);

        abort_unless(SessionPdfSlot::isValid($slot) && SessionPdfSlot::isMirrorable($slot), 404);

        $label = SessionPdfSlot::config($slot)['label'];
        $deleted = $pdfs->deleteLocal($legislativeSession, $slot);

        return redirect()
            ->route('ob.sessions.edit', $legislativeSession)
            ->with('status', $deleted
                ? $label.' local file removed.'
                : 'No local '.$label.' file to remove.');
    }
}
