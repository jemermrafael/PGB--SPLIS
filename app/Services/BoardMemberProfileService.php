<?php

namespace App\Services;

use App\Enums\CommitteeMembershipRole;
use App\Models\BoardMember;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use Illuminate\Support\Collection;

class BoardMemberProfileService
{
    /**
     * @return array{
     *     roles: array<string, Collection<int, CommitteeMembership>>,
     *     otherTerms: Collection<int, array{term: CommitteeTerm, roles: array<string, Collection<int, CommitteeMembership>>}>
     * }
     */
    public function build(BoardMember $boardMember, ?CommitteeTerm $selectedTerm = null): array
    {
        $selectedTerm ??= CommitteeTerm::currentOrCreate();

        $memberships = $boardMember->committeeMemberships()
            ->with(['committee', 'term'])
            ->get();

        $byTerm = $memberships->groupBy('committee_term_id')->toBase();

        $roles = $this->rolesGrouped($byTerm->get($selectedTerm->id) ?? collect());

        $otherTerms = $byTerm
            ->except([$selectedTerm->id])
            ->map(function (Collection $termMemberships, int|string $termId) {
                $term = $termMemberships->first()?->term;

                if ($term === null) {
                    return null;
                }

                return [
                    'term' => $term,
                    'roles' => $this->rolesGrouped($termMemberships),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $entry) => $entry['term']->year_from ?? 0)
            ->values();

        return [
            'roles' => $roles,
            'otherTerms' => $otherTerms,
        ];
    }

    /**
     * @return array<string, Collection<int, CommitteeMembership>>
     */
    protected function rolesGrouped(Collection $memberships): array
    {
        $sorted = $memberships
            ->sortBy(fn (CommitteeMembership $membership) => [
                $membership->committee?->sort_order ?? 9999,
                $membership->committee?->name ?? '',
            ])
            ->values();

        return [
            CommitteeMembershipRole::Chair->value => $sorted->where('role', CommitteeMembershipRole::Chair)->values(),
            CommitteeMembershipRole::ViceChair->value => $sorted->where('role', CommitteeMembershipRole::ViceChair)->values(),
            CommitteeMembershipRole::Member->value => $sorted->where('role', CommitteeMembershipRole::Member)->values(),
        ];
    }
}
