<?php

namespace App\Services;

use App\Enums\CommitteeMembershipRole;
use App\Enums\ObBlockType;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Support\AgendaDeadline;
use App\Support\CommitteeTermSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BoardMemberDashboardService
{
    public function __construct(
        protected BoardMemberRosterService $rosterService,
    ) {}

    /**
     * Election terms where the board member has at least one committee assignment.
     *
     * @return Collection<int, CommitteeTerm>
     */
    public function termsWithAssignmentsFor(User $user): Collection
    {
        $boardMember = $user->boardMember;

        if ($boardMember === null) {
            return collect();
        }

        $termIds = CommitteeMembership::query()
            ->where('board_member_id', $boardMember->id)
            ->distinct()
            ->pluck('committee_term_id');

        if ($termIds->isEmpty()) {
            return collect();
        }

        return $this->availableTerms()
            ->filter(fn (CommitteeTerm $term) => $termIds->contains($term->id))
            ->values();
    }

    public function resolveTermForUser(User $user, ?int $requestedTermId = null): CommitteeTerm
    {
        $memberTerms = $this->termsWithAssignmentsFor($user);

        if ($memberTerms->isEmpty()) {
            return $this->resolveTerm($requestedTermId);
        }

        if ($requestedTermId && $memberTerms->contains(fn (CommitteeTerm $term) => $term->id === $requestedTermId)) {
            return $memberTerms->firstWhere('id', $requestedTermId);
        }

        return $memberTerms->firstWhere('is_current', true) ?? $memberTerms->first();
    }

    public function resolveTerm(?int $requestedTermId = null): CommitteeTerm
    {
        return CommitteeTermSelection::resolve($requestedTermId)['selectedTerm'];
    }

    /**
     * @return Collection<int, CommitteeTerm>
     */
    public function availableTerms(): Collection
    {
        return CommitteeTermSelection::resolve()['terms'];
    }

    /**
     * @return Collection<int, array{committee: Committee, role: CommitteeMembershipRole, role_label: string}>
     */
    public function committeeAssignmentsFor(User $user, ?CommitteeTerm $term = null): Collection
    {
        $boardMember = $user->boardMember;

        if ($boardMember === null) {
            return collect();
        }

        $term ??= $this->resolveTerm();
        $termId = $term->id;

        return CommitteeMembership::query()
            ->with('committee')
            ->where('board_member_id', $boardMember->id)
            ->where('committee_term_id', $termId)
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
     * @return array{chair: Collection<int, array{committee: Committee, role: CommitteeMembershipRole, role_label: string}>, vice_chair: Collection<int, array{committee: Committee, role: CommitteeMembershipRole, role_label: string}>, member: Collection<int, array{committee: Committee, role: CommitteeMembershipRole, role_label: string}>}
     */
    public function assignmentsGroupedByRole(User $user, ?CommitteeTerm $term = null): array
    {
        $assignments = $this->committeeAssignmentsFor($user, $term);

        return [
            'chair' => $assignments->filter(fn (array $a) => $a['role'] === CommitteeMembershipRole::Chair)->values(),
            'vice_chair' => $assignments->filter(fn (array $a) => $a['role'] === CommitteeMembershipRole::ViceChair)->values(),
            'member' => $assignments->filter(fn (array $a) => $a['role'] === CommitteeMembershipRole::Member)->values(),
        ];
    }

    /**
     * Full term roster for a committee the member belongs to.
     *
     * @return Collection<string, Collection<int, CommitteeMembership>>
     */
    public function rosterForCommittee(Committee $committee, CommitteeTerm $term): Collection
    {
        return $committee->memberships()
            ->where('committee_term_id', $term->id)
            ->with('boardMember')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (CommitteeMembership $membership) => $membership->role->value);
    }

    /**
     * Agenda items from the member's committees that appear on a session OB.
     *
     * @return Collection<int, AgendaItem>
     */
    public function myCommitteeItemsOnSession(User $user, LegislativeSession $session): Collection
    {
        $committeeNames = $this->committeesFor($user)
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => mb_strtolower((string) $name))
            ->values();

        if ($committeeNames->isEmpty()) {
            return collect();
        }

        $session->loadMissing('obDocument.blocks.agendaItem');

        return $session->obDocument?->blocks
            ?->filter(fn ($block) => $block->agendaItem !== null)
            ->map(fn ($block) => $block->agendaItem)
            ->filter(function (AgendaItem $agendaItem) use ($committeeNames) {
                $committee = mb_strtolower((string) ($agendaItem->committee_referred ?? ''));

                return $committeeNames->contains(fn ($name) => $name !== '' && str_contains($committee, $name));
            })
            ->unique('id')
            ->values() ?? collect();
    }

    /**
     * @return array{committee: Committee, role: CommitteeMembershipRole, role_label: string}|null
     */
    public function membershipForCommittee(User $user, Committee $committee, ?CommitteeTerm $term = null): ?array
    {
        return $this->committeeAssignmentsFor($user, $term)
            ->first(fn (array $assignment) => $assignment['committee']->id === $committee->id);
    }

    /**
     * @return Builder<AgendaItem>
     */
    public function agendaQueryForCommittee(User $user, Committee $committee, ?CommitteeTerm $term = null): Builder
    {
        abort_unless($this->membershipForCommittee($user, $committee, $term) !== null, 403);

        return AgendaItem::query()
            ->where('committee_referred', 'like', '%'.$committee->name.'%');
    }

    /**
     * @return array<string, int>
     */
    public function agendaStatsForCommittee(User $user, Committee $committee, ?CommitteeTerm $term = null): array
    {
        $base = $this->agendaQueryForCommittee($user, $committee, $term);

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
    public function committeesFor(User $user, ?CommitteeTerm $term = null): Collection
    {
        $boardMember = $user->boardMember;

        if ($boardMember === null) {
            return collect();
        }

        $term ??= $this->resolveTerm();

        $committeeIds = CommitteeMembership::query()
            ->where('board_member_id', $boardMember->id)
            ->where('committee_term_id', $term->id)
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
     * Agendas referred to committees where the member is Chair.
     *
     * @return Builder<AgendaItem>
     */
    public function chairmanshipAgendaQueryFor(User $user): Builder
    {
        $committees = $this->assignmentsGroupedByRole($user)['chair']
            ->pluck('committee')
            ->filter();

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
     * Chairmanship agendas that do not yet have a committee report file/link.
     *
     * @return Builder<AgendaItem>
     */
    public function chairmanshipAgendasNeedingReportQueryFor(User $user): Builder
    {
        return $this->chairmanshipAgendaQueryFor($user)
            ->where(function (Builder $query): void {
                $query->whereNull('committee_report_pdf_path')
                    ->orWhere('committee_report_pdf_path', '');
            })
            ->where(function (Builder $query): void {
                $query->whereNull('committee_report_url')
                    ->orWhere('committee_report_url', '');
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
            ->notifiableToBoardMembers()
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

        return $this->rosterService->orderedActiveMembers($term);
    }

    /**
     * Regular unassigned business on an Order of Business session (for referral).
     *
     * @return Collection<int, AgendaItem>
     */
    public function incomingForReferralOnSession(?LegislativeSession $session): Collection
    {
        if ($session === null) {
            return collect();
        }

        $session->loadMissing('obDocument.blocks.agendaItem');

        $blocks = $session->obDocument?->blocks;
        if ($blocks === null) {
            return collect();
        }

        return $blocks
            ->filter(fn ($block) => $block->type === ObBlockType::UnassignedAgenda)
            ->filter(fn ($block) => ($block->content['kind'] ?? 'regular') !== 'urgent')
            ->map(fn ($block) => $block->agendaItem)
            ->filter()
            ->unique('id')
            ->values();
    }
}
