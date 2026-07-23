<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Services\BoardMemberDashboardService;
use App\Services\ObDocumentService;
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

    public function show(LegislativeSession $legislativeSession, ObDocumentService $obDocuments): View
    {
        abort_unless(auth()->user()?->canRecordAttendance(), 403);

        $obDocuments->syncSessionGuestsFromDocument($legislativeSession);
        $legislativeSession->refresh();

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
            'status' => ['nullable', 'array'],
            'status.*' => ['nullable', 'string', 'in:present,absent,ob,excused'],
            'remarks' => ['nullable', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:500'],
            'guests' => ['nullable', 'array'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
            'guests.*.remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $statuses = [];
        foreach ($validated['status'] ?? [] as $memberId => $value) {
            $statuses[(int) $memberId] = (string) $value;
        }

        $notes = [];
        foreach ($validated['remarks'] ?? [] as $memberId => $value) {
            $notes[(int) $memberId] = trim((string) $value) ?: null;
        }

        $this->attendanceService->saveForSession(
            $legislativeSession,
            $statuses,
            (int) $request->user()->id,
            $notes,
        );

        $guests = collect($validated['guests'] ?? [])
            ->map(fn (array $guest) => [
                'name' => trim((string) ($guest['name'] ?? '')),
                'remarks' => trim((string) ($guest['remarks'] ?? '')),
            ])
            ->filter(fn (array $guest) => $guest['name'] !== '' || $guest['remarks'] !== '')
            ->values()
            ->all();

        $legislativeSession->update([
            'guests' => $guests !== [] ? $guests : null,
        ]);

        return redirect()
            ->route('ob.sessions.attendance', $legislativeSession)
            ->with('status', 'Attendance saved for this session.');
    }

    public function monthlyReport(Request $request): View
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $currentTermId = \App\Models\CommitteeTerm::query()->current()->value('id');

        return view('order-of-business.sessions.attendance-monthly', [
            'year' => $year,
            'month' => $month,
            'currentTermId' => $currentTermId,
            'summary' => $this->attendanceService->monthlySummary($year, $month),
            'sessions' => $this->attendanceService->sessionsForMonth($year, $month),
        ]);
    }

    public function monthlyMaker(Request $request): View
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $sheet = $this->attendanceService->ensureMonthlySheet($year, $month, $request->user()?->id);
        $payload = $this->attendanceService->monthlyPrintPayload($year, $month, $sheet);

        return view('order-of-business.sessions.attendance-monthly-maker', [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $payload['month_label'],
            'sheet' => $sheet,
            'content' => $sheet->normalizedContent(),
        ]);
    }

    public function monthlyUpdate(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'prepared_by.name' => ['nullable', 'string', 'max:255'],
            'prepared_by.title' => ['nullable', 'string', 'max:255'],
            'noted_by.name' => ['nullable', 'string', 'max:255'],
            'noted_by.title' => ['nullable', 'string', 'max:255'],
            'approved_by.name' => ['nullable', 'string', 'max:255'],
            'approved_by.title' => ['nullable', 'string', 'max:255'],
        ]);

        $sheet = $this->attendanceService->ensureMonthlySheet(
            (int) $validated['year'],
            (int) $validated['month'],
            $request->user()?->id,
        );

        $sheet = $this->attendanceService->updateMonthlySheet(
            $sheet,
            $validated,
            $request->user()?->id,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Saved',
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('ob.sessions.attendance.monthly.maker', [
                'year' => $sheet->year,
                'month' => $sheet->month,
            ])
            ->with('status', 'Signatories saved.');
    }

    public function monthlyPrint(Request $request): View
    {
        abort_unless($request->user()?->canRecordAttendance(), 403);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $sheet = $this->attendanceService->ensureMonthlySheet($year, $month, $request->user()?->id);
        $payload = $this->attendanceService->monthlyPrintPayload($year, $month, $sheet);

        return view('order-of-business.sessions.attendance-monthly-print', [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $payload['month_label'],
            'payload' => $payload,
            'isEmbeddedPreview' => $request->boolean('embed'),
        ]);
    }
}
