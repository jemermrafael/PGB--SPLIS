<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Services\SessionPdfMirrorService;
use Illuminate\Http\RedirectResponse;

class SessionPdfMirrorController extends Controller
{
    public function __invoke(LegislativeSession $legislativeSession, SessionPdfMirrorService $mirror): RedirectResponse
    {
        $this->authorize('update', $legislativeSession);

        $result = $mirror->mirrorAllFor($legislativeSession, overwrite: false, userId: auth()->id());

        if ($result['mirrored'] > 0 && $result['failed'] === 0) {
            $message = $result['mirrored'] === 1
                ? ($result['messages'][0] ?? 'Session document mirrored.')
                : $result['mirrored'].' session document(s) mirrored from Drive.';
        } elseif ($result['mirrored'] > 0) {
            $message = $result['mirrored'].' session document(s) mirrored. '.$result['failed'].' failed.';
        } elseif ($result['failed'] > 0) {
            $message = implode(' ', $result['messages']);
        } else {
            $message = $result['messages'][0] ?? 'All linked session documents are already stored locally.';
        }

        return redirect()
            ->route('ob.sessions.edit', $legislativeSession)
            ->with($result['failed'] > 0 && $result['mirrored'] === 0 ? 'error' : 'status', $message);
    }
}
