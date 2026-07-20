<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Models\LegislativeSessionCommitteeReportFile;
use App\Services\SessionCommitteeReportFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionCommitteeReportFileController extends Controller
{
    public function __invoke(
        LegislativeSession $legislativeSession,
        LegislativeSessionCommitteeReportFile $file,
        SessionCommitteeReportFileService $files,
    ): StreamedResponse {
        $this->authorize('view', $legislativeSession);

        abort_unless((int) $file->legislative_session_id === (int) $legislativeSession->id, 404);

        return $files->stream($file);
    }
}
