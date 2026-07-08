<?php

namespace App\Services;

use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use Illuminate\Support\Collection;

class SessionAttendanceService
{
    public function __construct(
        protected BoardMemberDashboardService $rosterService,
    ) {}

    /**
     * @param  array<int, bool>  $presenceByMemberId
     */
    public function saveForSession(LegislativeSession $session, array $presenceByMemberId, int $recordedBy): void
    {
        foreach ($presenceByMemberId as $boardMemberId => $isPresent) {
            SessionAttendance::query()->updateOrCreate(
                [
                    'legislative_session_id' => $session->id,
                    'board_member_id' => (int) $boardMemberId,
                ],
                [
                    'is_present' => (bool) $isPresent,
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
            $total = $sessions->count();

            foreach ($sessions as $session) {
                $attendance = $session->attendances->firstWhere('board_member_id', $member->id);
                if ($attendance?->is_present) {
                    $present++;
                }
            }

            $summary[] = [
                'member' => $member,
                'present' => $present,
                'total' => $total,
            ];
        }

        return $summary;
    }
}
