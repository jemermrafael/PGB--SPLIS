<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Models\LegislativeSessionCommitteeReportFile;
use App\Services\SessionCommitteeReportFileService;
use Illuminate\Http\RedirectResponse;

class SessionCommitteeReportFileDeleteController extends Controller
{
    public function __invoke(
        LegislativeSession $legislativeSession,
        LegislativeSessionCommitteeReportFile $file,
        SessionCommitteeReportFileService $files,
    ): RedirectResponse {
        $this->authorize('update', $legislativeSession);

        abort_unless((int) $file->legislative_session_id === (int) $legislativeSession->id, 404);

        $files->delete($file);

        return redirect()
            ->route('ob.sessions.edit', $legislativeSession)
            ->with('status', 'Committee report file removed.');
    }
}
