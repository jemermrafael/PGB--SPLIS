<?php

namespace App\Services;

use App\Models\BoardMemberTerm;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\MonthlyAttendanceSheet;
use App\Models\SessionAttendance;
use Illuminate\Support\Collection;

class SessionAttendanceService
{
    /** Pad RS/SS columns to at least this many; extra sessions still add columns. */
    public const PRINT_SESSION_COLUMNS = 4;

    public function __construct(
        protected BoardMemberDashboardService $rosterService,
        protected BoardMemberRosterService $boardMemberRoster,
    ) {}

    /**
     * @return array{name: string, title: string}
     */
    public function defaultPreparedBy(): array
    {
        return [
            'name' => 'DESIREE S. SEVILLA',
            'title' => 'Administrative Assistant I',
        ];
    }

    /**
     * @return array{name: string, title: string}
     */
    public function defaultNotedBy(): array
    {
        return [
            'name' => 'ATTY. MARK LORENZ C. QUEZON',
            'title' => 'Secretary to the SP',
        ];
    }

    /**
     * @return array{name: string, title: string}
     */
    public function defaultApprovedBy(): array
    {
        return [
            'name' => 'MA. CRISTINA M. GARCIA',
            'title' => 'Vice Governor',
        ];
    }

    public function ensureMonthlySheet(int $year, int $month, ?int $userId = null): MonthlyAttendanceSheet
    {
        return MonthlyAttendanceSheet::query()->firstOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'content' => [
                    'prepared_by' => $this->defaultPreparedBy(),
                    'noted_by' => $this->defaultNotedBy(),
                    'approved_by' => $this->defaultApprovedBy(),
                    'member_remarks' => [],
                ],
                'updated_by' => $userId,
            ],
        );
    }

    /**
     * @param  array{
     *   prepared_by?: array{name?: string|null, title?: string|null},
     *   noted_by?: array{name?: string|null, title?: string|null},
     *   approved_by?: array{name?: string|null, title?: string|null},
     *   member_remarks?: array<string, string|null>
     * }  $payload
     */
    public function updateMonthlySheet(MonthlyAttendanceSheet $sheet, array $payload, ?int $userId = null): MonthlyAttendanceSheet
    {
        $content = $sheet->normalizedContent();

        foreach (['prepared_by', 'noted_by', 'approved_by'] as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                continue;
            }
            $content[$key] = [
                'name' => trim((string) ($payload[$key]['name'] ?? $content[$key]['name'] ?? '')),
                'title' => trim((string) ($payload[$key]['title'] ?? $content[$key]['title'] ?? '')),
            ];
        }

        if (array_key_exists('member_remarks', $payload) && is_array($payload['member_remarks'])) {
            $content['member_remarks'] = collect($payload['member_remarks'])
                ->mapWithKeys(fn ($value, $key) => [(string) $key => trim((string) $value)])
                ->filter(fn (string $value) => $value !== '')
                ->all();
        }

        $sheet->forceFill([
            'content' => $content,
            'updated_by' => $userId,
        ])->save();

        return $sheet->fresh();
    }

    /**
     * @param  array<int, string>  $statusByMemberId
     * @param  array<int, string|null>  $notesByMemberId
     */
    public function saveForSession(
        LegislativeSession $session,
        array $statusByMemberId,
        int $recordedBy,
        array $notesByMemberId = [],
    ): void {
        foreach ($statusByMemberId as $boardMemberId => $status) {
            $memberId = (int) $boardMemberId;
            $normalized = match (strtolower(trim((string) $status))) {
                SessionAttendance::STATUS_PRESENT => SessionAttendance::STATUS_PRESENT,
                SessionAttendance::STATUS_OB => SessionAttendance::STATUS_OB,
                SessionAttendance::STATUS_EXCUSED => SessionAttendance::STATUS_EXCUSED,
                default => SessionAttendance::STATUS_ABSENT,
            };

            $notes = isset($notesByMemberId[$memberId])
                ? trim((string) $notesByMemberId[$memberId])
                : '';
            $notes = $notes === '' ? null : $notes;

            SessionAttendance::query()->updateOrCreate(
                [
                    'legislative_session_id' => $session->id,
                    'board_member_id' => $memberId,
                ],
                [
                    ...SessionAttendance::attributesForStatus($normalized),
                    'notes' => $notes,
                    'recorded_by' => $recordedBy,
                ],
            );
        }
    }

    /**
     * @return Collection<int, SessionAttendance>
     */
    public function forSession(LegislativeSession $session): Collection
    {
        return SessionAttendance::query()
            ->where('legislative_session_id', $session->id)
            ->with('boardMember')
            ->get()
            ->keyBy('board_member_id');
    }

    /**
     * @return Collection<int, LegislativeSession>
     */
    public function sessionsForMonth(int $year, int $month): Collection
    {
        return LegislativeSession::query()
            ->with(['attendances.boardMember'])
            ->whereYear('session_date', $year)
            ->whereMonth('session_date', $month)
            ->orderBy('session_date')
            ->get();
    }

    public function monthlySummary(int $year, int $month): array
    {
        $sessions = $this->sessionsForMonth($year, $month);
        $roster = $this->rosterService->rosterForAttendance();

        $summary = [];

        foreach ($roster as $member) {
            $present = 0;
            $sessionAttendance = [];
            $monthRemarks = [];

            foreach ($sessions as $session) {
                $attendance = $session->attendances->firstWhere('board_member_id', $member->id);
                $status = $attendance?->status();

                if ($status === SessionAttendance::STATUS_PRESENT) {
                    $present++;
                }

                $sessionAttendance[$session->id] = $status;

                $note = $attendance?->displayNotes() ?? '';
                if ($note !== '') {
                    $monthRemarks[] = $note;
                }
            }

            $uniqueRemarks = collect($monthRemarks)->unique()->values()->all();

            $summary[] = [
                'member' => $member,
                'present' => $present,
                'total' => $sessions->count(),
                'sessions' => $sessionAttendance,
                'remarks' => $uniqueRemarks !== [] ? implode(', ', $uniqueRemarks) : '',
            ];
        }

        return $summary;
    }

    /**
     * Printable monthly sheet: name column, one RS/SS column per session held, Remarks.
     *
     * @return array{
     *   title: string,
     *   month_label: string,
     *   sessions: Collection<int, LegislativeSession|null>,
     *   rows: list<array<string, mixed>>,
     *   prepared_by: array{name: string, title: string},
     *   noted_by: array{name: string, title: string},
     *   approved_by: array{name: string, title: string}
     * }
     */
    public function monthlyPrintPayload(int $year, int $month, ?MonthlyAttendanceSheet $sheet = null): array
    {
        $sheet ??= $this->ensureMonthlySheet($year, $month);
        $sheetContent = $sheet->normalizedContent();

        if (blank($sheetContent['prepared_by']['name'] ?? null)) {
            $sheetContent['prepared_by'] = $this->defaultPreparedBy();
        }
        if (blank($sheetContent['noted_by']['name'] ?? null)) {
            $sheetContent['noted_by'] = $this->defaultNotedBy();
        }
        if (blank($sheetContent['approved_by']['name'] ?? null)) {
            $sheetContent['approved_by'] = $this->defaultApprovedBy();
        }

        $sessions = $this->sessionsForMonth($year, $month);
        $term = CommitteeTerm::query()->current()->first() ?? CommitteeTerm::currentOrCreate();
        $grouped = $this->boardMemberRoster->rosterGroupedByDistrict($term);

        $sessionSlots = collect($sessions->all());
        while ($sessionSlots->count() < self::PRINT_SESSION_COLUMNS) {
            $sessionSlots->push(null);
        }

        $summaryByMemberId = collect($this->monthlySummary($year, $month))
            ->keyBy(fn (array $row) => $row['member']->id);

        $rows = [];

        foreach ($grouped as $district => $assignments) {
            $activeAssignments = $assignments
                ->filter(fn (BoardMemberTerm $assignment) => $assignment->is_active && $assignment->boardMember !== null)
                ->values();

            if ($activeAssignments->isEmpty()) {
                continue;
            }

            if ($district !== 'Vice Governor') {
                $rows[] = [
                    'type' => 'section',
                    'label' => $this->sectionLabel((string) $district),
                ];
            }

            foreach ($activeAssignments as $assignment) {
                $member = $assignment->boardMember;
                $summary = $summaryByMemberId->get($member->id);
                $marks = [];

                foreach ($sessionSlots as $session) {
                    if (! $session instanceof LegislativeSession) {
                        $marks[] = null;
                        continue;
                    }

                    $marks[] = $summary['sessions'][$session->id] ?? null;
                }

                $rows[] = [
                    'type' => 'member',
                    'member_id' => $member->id,
                    'name' => $member->officialName(),
                    'subtitle' => $this->memberSubtitle((string) $district, $assignment),
                    'marks' => $marks,
                    'remarks' => $summary['remarks'] ?? '',
                ];
            }
        }

        $monthLabel = \Carbon\Carbon::create($year, $month, 1)->format('F Y');

        return [
            'title' => 'ATTENDANCE OF VICE GOVERNOR AND BOARD MEMBERS',
            'month_label' => $monthLabel,
            'sessions' => $sessionSlots,
            'rows' => $rows,
            'prepared_by' => $sheetContent['prepared_by'],
            'noted_by' => $sheetContent['noted_by'],
            'approved_by' => $sheetContent['approved_by'],
        ];
    }

    public function sessionColumnCode(?LegislativeSession $session): string
    {
        if ($session === null) {
            return '';
        }

        return strtolower((string) $session->session_kind) === 'special' ? 'SS' : 'RS';
    }

    public function sessionColumnDay(?LegislativeSession $session): string
    {
        if ($session?->session_date === null) {
            return '';
        }

        return (string) $session->session_date->day;
    }

    protected function sectionLabel(string $district): string
    {
        return match ($district) {
            '1st District' => '1st District Board Members',
            '2nd District' => '2nd District Board Members',
            '3rd District' => '3rd District Board Members',
            'Ex Officio' => 'Ex Officio Board Member',
            default => $district.' Board Members',
        };
    }

    protected function memberSubtitle(string $district, BoardMemberTerm $assignment): ?string
    {
        if ($district === 'Vice Governor') {
            return 'Provincial Vice-Governor';
        }

        if ($district === 'Ex Officio') {
            $title = trim((string) ($assignment->ex_officio_title ?? ''));

            return $title !== '' ? '('.$title.')' : null;
        }

        return null;
    }
}
