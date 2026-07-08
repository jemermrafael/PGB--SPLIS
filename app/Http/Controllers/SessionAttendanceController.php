<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Models\UserNotification;
use App\Services\BoardMemberDashboardService;
use App\Services\SessionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionAttendanceController extends Controller
{
    public function __construct(
        protected SessionAttendanceService $attendanceService,
        protected BoardMemberDashboardService $dashboardService,
    ) {}

    public function show(LegislativeSession $legislativeSession): View
    {
        abort_unless(auth()->user()?->canRecordAttendance(), 403);

        $roster = $this->dashboardService->rosterForAttendance();
        $attendances = $this->attendanceService->forSession($legislativeSession);
        $termId = \App\Models\CommitteeTerm::query()->current()->value('id');

        return view('order-of-business.sessions.attendance', [
            'session' => $legislativeSession,
            'roster' => $roster,
            'attendances' => $attendances,
            'termId' => $termId,
        ]);
    }

    public function update(Request $request, LegislativeSession $legislativeSession): RedirectResponse
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $validated = $request->validate([
            'presence' => ['nullable', 'array'],
            'presence.*' => ['nullable', 'boolean'],
        ]);

        $presence = [];
        foreach ($validated['presence'] ?? [] as $memberId => $value) {
            $presence[(int) $memberId] = (bool) $value;
        }

        $this->attendanceService->saveForSession(
            $legislativeSession,
            $presence,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('ob.sessions.attendance', $legislativeSession)
            ->with('status', 'Attendance saved for this session.');
    }

    public function monthlyReport(Request $request): View
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return view('order-of-business.sessions.attendance-monthly', [
            'year' => $year,
            'month' => $month,
            'summary' => $this->attendanceService->monthlySummary($year, $month),
            'sessions' => $this->attendanceService->sessionsForMonth($year, $month),
        ]);
    }
}
