<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardMemberResolutionsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_member_resolutions_list_only_chairmanship_connected_items(): void
    {
        [$user, $chairCommittee, $term, $boardMember] = $this->linkedChair();

        $memberCommittee = Committee::query()->create([
            'name' => 'Energy, Water, and Public Utilities',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        CommitteeMembership::query()->create([
            'committee_id' => $memberCommittee->id,
            'board_member_id' => $boardMember->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Member,
            'sort_order' => 0,
        ]);

        $chairResolution = Resolution::query()->create([
            'resolution_no' => '101',
            'resolution_title' => 'Chairmanship connected resolution',
            'series' => 2026,
            'committee' => $chairCommittee->name,
            'created_by' => $user->id,
        ]);
        $memberResolution = Resolution::query()->create([
            'resolution_no' => '202',
            'resolution_title' => 'Membership-only connected resolution',
            'series' => 2026,
            'committee' => $memberCommittee->name,
            'created_by' => $user->id,
        ]);

        AgendaItem::query()->create([
            'title' => 'Chair agenda with resolution',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_DONE,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'resolution_id' => $chairResolution->id,
            'created_by' => $user->id,
        ]);
        AgendaItem::query()->create([
            'title' => 'Member agenda with resolution',
            'committee_referred' => $memberCommittee->name,
            'status' => AgendaItem::STATUS_DONE,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'resolution_id' => $memberResolution->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('board-member.resolutions.index'))
            ->assertOk()
            ->assertSee('Resolutions')
            ->assertSee('My Agenda');

        $this->actingAs($user)
            ->getJson(route('board-member.resolutions.search'))
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Chairmanship connected resolution'])
            ->assertJsonMissing(['subject' => 'Membership-only connected resolution'])
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * @return array{0: User, 1: Committee, 2: CommitteeTerm, 3: BoardMember}
     */
    protected function linkedChair(): array
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);

        $boardMember = BoardMember::query()->create([
            'name' => 'Resolution Chair',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $committee = Committee::query()->create([
            'name' => 'Housing and Land Use',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        CommitteeMembership::query()->create([
            'committee_id' => $committee->id,
            'board_member_id' => $boardMember->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $boardMember->id,
            'username' => 'bm_reso_chair',
            'is_active' => true,
            'name' => 'Hon. Resolution Chair',
        ]);

        return [$user, $committee, $term, $boardMember];
    }
}
