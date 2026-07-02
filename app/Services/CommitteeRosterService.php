<?php

namespace App\Services;

use App\Enums\CommitteeMembershipRole;
use App\Models\BoardMember;
use App\Models\BoardMemberTerm;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\CommitteeTermSecretary;
use Illuminate\Support\Facades\DB;

class CommitteeRosterService
{
    /**
     * @param  array{
     *     chair_id?: int|null,
     *     vice_chair_id?: int|null,
     *     secretary?: string|null,
     *     member_ids?: list<int>
     * }  $roster
     */
    public function saveRoster(Committee $committee, CommitteeTerm $term, array $roster): void
    {
        DB::transaction(function () use ($committee, $term, $roster): void {
            $committee->memberships()
                ->where('committee_term_id', $term->id)
                ->delete();

            $sortOrder = 0;

            $this->assignRole($committee, $term, CommitteeMembershipRole::Chair, $roster['chair_id'] ?? null, $sortOrder);
            $this->assignRole($committee, $term, CommitteeMembershipRole::ViceChair, $roster['vice_chair_id'] ?? null, $sortOrder);

            foreach ($roster['member_ids'] ?? [] as $memberId) {
                $this->assignRole($committee, $term, CommitteeMembershipRole::Member, (int) $memberId, $sortOrder++);
            }

            $this->saveSecretary($committee, $term, $roster['secretary'] ?? null);

            if ($term->is_current) {
                $this->syncLegacyTextFields($committee, $term);
            }
        });
    }

    public function syncLegacyTextFields(Committee $committee, CommitteeTerm $term): void
    {
        $committee->refresh();

        $memberLines = $committee->memberDisplayNames($term->id, allowLegacy: false);

        $committee->forceFill([
            'chair' => $committee->chairDisplayName($term->id, allowLegacy: false) ?: null,
            'vice_chair' => $committee->viceChairDisplayName($term->id, allowLegacy: false) ?: null,
            'secretary' => $committee->secretaryDisplayName($term->id, allowLegacy: false) ?: null,
            'members' => $memberLines !== [] ? implode("\n", $memberLines) : null,
        ])->saveQuietly();
    }

    public function findOrCreateBoardMemberByName(string $name): BoardMember
    {
        $name = trim($name);

        $existing = BoardMember::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $member = BoardMember::query()->create([
            'name' => $name,
            'is_active' => true,
        ]);

        $term = CommitteeTerm::currentOrCreate();

        BoardMemberTerm::query()->firstOrCreate(
            [
                'board_member_id' => $member->id,
                'committee_term_id' => $term->id,
            ],
            [
                'is_active' => true,
            ],
        );

        return $member;
    }

    /**
     * @return array{
     *     chair_id: int|null,
     *     vice_chair_id: int|null,
     *     secretary: string,
     *     member_ids: list<int>
     * }
     */
    public function rosterForTerm(Committee $committee, CommitteeTerm $term): array
    {
        $memberships = $committee->memberships()
            ->where('committee_term_id', $term->id)
            ->orderBy('sort_order')
            ->get();

        return [
            'chair_id' => $memberships->firstWhere('role', CommitteeMembershipRole::Chair)?->board_member_id,
            'vice_chair_id' => $memberships->firstWhere('role', CommitteeMembershipRole::ViceChair)?->board_member_id,
            'secretary' => $committee->secretaryDisplayName($term->id),
            'member_ids' => $memberships
                ->where('role', CommitteeMembershipRole::Member)
                ->pluck('board_member_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }

    protected function saveSecretary(Committee $committee, CommitteeTerm $term, ?string $name): void
    {
        $name = trim((string) $name);

        if ($name === '') {
            $committee->termSecretaries()
                ->where('committee_term_id', $term->id)
                ->delete();

            return;
        }

        CommitteeTermSecretary::query()->updateOrCreate(
            [
                'committee_id' => $committee->id,
                'committee_term_id' => $term->id,
            ],
            ['name' => $name],
        );
    }

    protected function assignRole(
        Committee $committee,
        CommitteeTerm $term,
        CommitteeMembershipRole $role,
        ?int $boardMemberId,
        int $sortOrder,
    ): void {
        if ($boardMemberId === null || $boardMemberId <= 0) {
            return;
        }

        CommitteeMembership::query()->create([
            'committee_id' => $committee->id,
            'board_member_id' => $boardMemberId,
            'committee_term_id' => $term->id,
            'role' => $role,
            'sort_order' => $sortOrder,
        ]);
    }
}
