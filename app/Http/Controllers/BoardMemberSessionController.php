<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;
use App\Services\BoardMemberDashboardService;
use Illuminate\View\View;

class BoardMemberSessionController extends Controller
{
    public function __construct(
        protected BoardMemberDashboardService $dashboard,
    ) {}

    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user->isBoardMember(), 403);

        $base = LegislativeSession::query()
            ->forBoardMemberSessionPackets()
            ->with([
                'obDocument',
                'attendances' => fn ($query) => $query->where('board_member_id', $user->board_member_id),
            ]);

        $upcomingSessions = (clone $base)
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->orderBy('id')
            ->limit(20)
            ->get();

        $recentPastSessions = (clone $base)
            ->whereDate('session_date', '<', now()->toDateString())
            ->orderByDesc('session_date')
            ->orderByDesc('session_time')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return view('board-member.sessions.index', [
            'upcomingSessions' => $upcomingSessions,
            'recentPastSessions' => $recentPastSessions,
            'attendanceLabelFor' => fn (LegislativeSession $session): string => $this->attendanceLabel(
                $session->attendances->first()
            ),
        ]);
    }

    public function show(LegislativeSession $session): View
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user->isBoardMember(), 403);
        abort_unless($session->isAvailableForBoardMemberSessionPacket(), 403);

        $session->load([
            'obDocument.blocks.agendaItem',
            'committeeReportFiles',
            'attendances' => fn ($query) => $query->where('board_member_id', $user->board_member_id),
        ]);

        $myAttendance = $session->attendances->first();
        $myItems = $this->dashboard->myCommitteeItemsOnSession($user, $session);
        $obDocument = $session->obDocument;
        $canViewOb = $obDocument !== null && $user->can('view', $obDocument);
        $committeeReportFiles = $session->committeeReportFiles->filter(fn ($file) => $file->existsLocally())->values();
        $committeeReportsDriveUrl = $session->committeeReportsDriveUrl();

        return view('board-member.sessions.show', [
            'session' => $session,
            'myAttendanceLabel' => $this->attendanceLabel($myAttendance),
            'myItemsOnSession' => $myItems,
            'sessionPdfRows' => $session->sessionPdfLinkRows(),
            'canViewOb' => $canViewOb,
            'committeeReportFiles' => $committeeReportFiles,
            'committeeReportsDriveUrl' => $committeeReportsDriveUrl,
            'hasCommitteeReportsFolder' => $committeeReportFiles->isNotEmpty() || filled($committeeReportsDriveUrl),
        ]);
    }

    protected function attendanceLabel(?SessionAttendance $attendance): string
    {
        if (! $attendance) {
            return 'Not recorded';
        }

        return match ($attendance->status()) {
            SessionAttendance::STATUS_PRESENT => 'Present',
            SessionAttendance::STATUS_OB => 'Official Business',
            SessionAttendance::STATUS_EXCUSED => 'Excused',
            default => 'Absent',
        };
    }
}
