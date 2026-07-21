<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Services\SessionPdfMirrorService;
use Illuminate\Http\RedirectResponse;

class SessionCommitteeReportsMirrorController extends Controller
{
    public function __invoke(
        LegislativeSession $legislativeSession,
        SessionPdfMirrorService $mirror,
    ): RedirectResponse {
        $this->authorize('update', $legislativeSession);

        $result = $mirror->mirrorCommitteeReportsFolder($legislativeSession, auth()->id());

        if ($result['mirrored'] > 0 && $result['failed'] === 0) {
            $message = $result['mirrored'] === 1
                ? '1 committee report downloaded from Drive.'
                : $result['mirrored'].' committee reports downloaded from Drive.';
            if ($result['skipped'] > 0) {
                $message .= ' '.$result['skipped'].' already local, skipped.';
            }
        } elseif ($result['mirrored'] > 0) {
            $message = $result['mirrored'].' downloaded. '.$result['failed'].' failed.';
            if ($result['messages'] !== []) {
                $message .= ' '.implode(' ', $result['messages']);
            }
        } elseif ($result['failed'] > 0) {
            $message = implode(' ', $result['messages']);
        } else {
            $message = $result['messages'][0] ?? 'No new committee report files to download.';
        }

        return redirect()
            ->route('ob.sessions.edit', $legislativeSession)
            ->with($result['failed'] > 0 && $result['mirrored'] === 0 ? 'error' : 'status', $message);
    }
}
