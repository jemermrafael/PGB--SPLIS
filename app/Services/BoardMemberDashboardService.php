<?php

namespace App\Services;

use App\Enums\CommitteeMembershipRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Support\AgendaDeadline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BoardMemberDashboardService
{
    /**
     * @return Collection<int, array{committee: Committee, role: CommitteeMembershipRole, role_label: string}>
     */
    public function committeeAssignmentsFor(User $user): Collection
    {
        $boardMember = $user->boardMember;

        if ($boardMember === null) {
            return collect();
        }

        $termId = CommitteeTerm::query()->current()->value('id');

        return CommitteeMembership::query()
            ->with('committee')
            ->where('board_member_id', $boardMember->id)
            ->when($termId, fn (Builder $query) => $query->where('committee_term_id', $termId))
            ->get()
            ->filter(fn (CommitteeMembership $membership) => $membership->committee !== null)
            ->sortBy(fn (CommitteeMembership $membership) => $membership->committee->sort_order ?? 999)
            ->map(fn (CommitteeMembership $membership) => [
                'committee' => $membership->committee,
                'role' => $membership->role,
                'role_label' => $membership->role->label(),
            ])
            ->values();
    }

    /**
     * @return array{committee: Committee, role: CommitteeMembershipRole, role_label: string}|null
     */
    public function membershipForCommittee(User $user, Committee $committee): ?array
    {
        return $this->committeeAssignmentsFor($user)
            ->first(fn (array $assignment) => $assignment['committee']->id === $committee->id);
    }

    /**
     * @return Builder<AgendaItem>
     */
    public function agendaQueryForCommittee(User $user, Committee $committee): Builder
    {
        abort_unless($this->membershipForCommittee($user, $committee) !== null, 403);

        return AgendaItem::query()
            ->where('committee_referred', 'like', '%'.$committee->name.'%');
    }

    /**
     * @return array<string, int>
     */
    public function agendaStatsForCommittee(User $user, Committee $committee): array
    {
        $base = $this->agendaQueryForCommittee($user, $committee);

        return [
            'pending' => (clone $base)->where('status', AgendaItem::STATUS_PENDING)->count(),
            'expiring_soon' => (clone $base)->expiringSoon()->count(),
            'due_soon' => (clone $base)->dueSoon()->count(),
            'done' => (clone $base)->where('status', AgendaItem::STATUS_DONE)->count(),
            'lapsed' => (clone $base)->where('status', AgendaItem::STATUS_LAPSED)->count(),
        ];
    }

    /**
     * @return Builder<LegislativeSession>
     */
    public function orderOfBusinessQuery(): Builder
    {
        return LegislativeSession::query()
            ->with('obDocument')
            ->visibleToBoardMembers()
            ->orderByDesc('session_date')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, Committee>
     */
    public function committeesFor(User $user): Collection
    {
        $boardMember = $user->boardMember;

        if ($boardMember === null) {
            return collect();
        }

        $termId = CommitteeTerm::query()->current()->value('id');

        $committeeIds = CommitteeMembership::query()
            ->where('board_member_id', $boardMember->id)
            ->when($termId, fn (Builder $query) => $query->where('committee_term_id', $termId))
            ->pluck('committee_id');

        return Committee::query()
            ->whereIn('id', $committeeIds)
            ->ordered()
            ->get();
    }

    /**
     * @return Builder<AgendaItem>
     */
    public function committeeAgendaQueryFor(User $user): Builder
    {
        $committees = $this->committeesFor($user);

        if ($committees->isEmpty()) {
            return AgendaItem::query()->whereRaw('0 = 1');
        }

        return AgendaItem::query()
            ->where(function (Builder $query) use ($committees): void {
                foreach ($committees as $committee) {
                    $query->orWhere('committee_referred', 'like', '%'.$committee->name.'%');
                }
            });
    }

    /**
     * @return Builder<AgendaItem>
     */
    public function agendaQueryFor(User $user): Builder
    {
        return $this->committeeAgendaQueryFor($user)
            ->orderByDesc('date_of_referral')
            ->orderByDesc('date_received')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, int>
     */
    public function agendaStatsFor(User $user): array
    {
        $base = $this->committeeAgendaQueryFor($user);

        return [
            'pending' => (clone $base)->where('status', AgendaItem::STATUS_PENDING)->count(),
            'expiring_soon' => (clone $base)->expiringSoon()->count(),
            'due_soon' => (clone $base)->dueSoon()->count(),
            'done' => (clone $base)->where('status', AgendaItem::STATUS_DONE)->count(),
            'lapsed' => (clone $base)->where('status', AgendaItem::STATUS_LAPSED)->count(),
        ];
    }

    /**
     * @return Builder<AgendaItem>
     */
    public function expiringSoonAgendaQueryFor(User $user): Builder
    {
        return $this->committeeAgendaQueryFor($user)
            ->expiringSoon()
            ->orderBy('due_date')
            ->orderBy('id');
    }

    /**
     * @return Collection<int, AgendaItem>
     */
    public function expiringSoonAgendasFor(User $user, int $limit = 8): Collection
    {
        return $this->expiringSoonAgendaQueryFor($user)->limit($limit)->get();
    }

    public function expiringSoonDays(): int
    {
        return AgendaDeadline::expiringSoonDays();
    }

    /**
     * @return Collection<int, LegislativeSession>
     */
    public function upcomingSessions(int $limit = 12): Collection
    {
        return LegislativeSession::query()
            ->with(['obDocument.blocks.agendaItem'])
            ->visibleToBoardMembers()
            ->where('session_date', '>=', now()->startOfMonth()->subMonths(1))
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, LegislativeSession>
     */
    public function orderOfBusinessSessions(int $limit = 20): Collection
    {
        return $this->orderOfBusinessQuery()
            ->limit($limit)
            ->get();
    }

    public function rosterForAttendance(): Collection
    {
        $term = CommitteeTerm::query()->current()->first() ?? CommitteeTerm::currentOrCreate();
        $termId = $term->id;

        return BoardMember::query()
            ->whereHas('termAssignments', function ($query) use ($termId): void {
                $query
                    ->where('committee_term_id', $termId)
                    ->where('is_active', true);
            })
            ->with(['termAssignments' => fn ($query) => $query->where('committee_term_id', $termId)])
            ->get()
            ->sortBy(function (BoardMember $member) use ($termId) {
                $district = $member->districtForTerm($termId) ?? $member->district ?? '';

                return match ($district) {
                    'Vice Governor' => '0',
                    '1st District' => '1',
                    '2nd District' => '2',
                    '3rd District' => '3',
                    default => '9'.$member->name,
                };
            })
            ->values();
    }
}
