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

    public function test_board_member_is_redirected_from_full_resolutions_index(): void
    {
        [$user] = $this->linkedChair();

        $this->actingAs($user)
            ->get(route('resolutions.index'))
            ->assertRedirect(route('board-member.resolutions.index'));
    }

    public function test_board_member_cannot_search_full_resolutions_archive(): void
    {
        [$user] = $this->linkedChair();

        $this->actingAs($user)
            ->getJson(route('resolutions.search'))
            ->assertForbidden();
    }

    public function test_board_member_resolutions_list_includes_all_committee_roles(): void
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

        $viceCommittee = Committee::query()->create([
            'name' => 'Ways and Means',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        CommitteeMembership::query()->create([
            'committee_id' => $viceCommittee->id,
            'board_member_id' => $boardMember->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::ViceChair,
            'sort_order' => 0,
        ]);

        $outsideCommittee = Committee::query()->create([
            'name' => 'Rules',
            'is_active' => true,
            'sort_order' => 4,
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
            'resolution_title' => 'Membership connected resolution',
            'series' => 2026,
            'committee' => $memberCommittee->name,
            'created_by' => $user->id,
        ]);
        $viceResolution = Resolution::query()->create([
            'resolution_no' => '303',
            'resolution_title' => 'Vice chair connected resolution',
            'series' => 2026,
            'committee' => $viceCommittee->name,
            'created_by' => $user->id,
        ]);
        $outsideResolution = Resolution::query()->create([
            'resolution_no' => '404',
            'resolution_title' => 'Outside committee resolution',
            'series' => 2026,
            'committee' => $outsideCommittee->name,
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
        AgendaItem::query()->create([
            'title' => 'Vice chair agenda with resolution',
            'committee_referred' => $viceCommittee->name,
            'status' => AgendaItem::STATUS_DONE,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'resolution_id' => $viceResolution->id,
            'created_by' => $user->id,
        ]);
        AgendaItem::query()->create([
            'title' => 'Outside agenda with resolution',
            'committee_referred' => $outsideCommittee->name,
            'status' => AgendaItem::STATUS_DONE,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'resolution_id' => $outsideResolution->id,
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
            ->assertJsonFragment(['subject' => 'Membership connected resolution'])
            ->assertJsonFragment(['subject' => 'Vice chair connected resolution'])
            ->assertJsonMissing(['subject' => 'Outside committee resolution'])
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($user)
            ->get(route('resolutions.show', $memberResolution))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('resolutions.show', $outsideResolution))
            ->assertForbidden();
    }

    public function test_board_member_can_view_resolution_tagged_to_their_committee(): void
    {
        [$user, , $term, $boardMember] = $this->linkedChair();

        $tourism = Committee::query()->create([
            'name' => 'Tourism',
            'is_active' => true,
            'sort_order' => 5,
        ]);
        CommitteeMembership::query()->create([
            'committee_id' => $tourism->id,
            'board_member_id' => $boardMember->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Member,
            'sort_order' => 0,
        ]);

        $publicInfo = Committee::query()->create([
            'name' => 'Public Information',
            'is_active' => true,
            'sort_order' => 6,
        ]);

        $resolution = Resolution::query()->create([
            'resolution_no' => '505',
            'resolution_title' => 'Tourism tagged resolution',
            'series' => 2026,
            'committee' => 'TOURISM',
            'created_by' => $user->id,
        ]);

        AgendaItem::query()->create([
            'title' => 'Referred to Public Information but outputs Tourism resolution',
            'committee_referred' => $publicInfo->name,
            'status' => AgendaItem::STATUS_DONE,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'resolution_id' => $resolution->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('resolutions.show', $resolution))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('board-member.resolutions.search'))
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Tourism tagged resolution']);
    }

    public function test_board_member_cannot_open_agenda_outside_their_committees(): void
    {
        [$user] = $this->linkedChair();

        $outside = Committee::query()->create([
            'name' => 'Public Information',
            'is_active' => true,
            'sort_order' => 9,
        ]);

        $agenda = AgendaItem::query()->create([
            'title' => 'Outside committee agenda',
            'committee_referred' => $outside->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('agenda.show', $agenda))
            ->assertForbidden();
    }

    public function test_board_member_resolution_prev_next_skips_inaccessible_items(): void
    {
        [$user, $chairCommittee] = $this->linkedChair();

        $allowedOlder = Resolution::query()->create([
            'resolution_no' => '10',
            'resolution_title' => 'Older allowed',
            'series' => 2026,
            'committee' => $chairCommittee->name,
            'created_by' => $user->id,
        ]);
        $blocked = Resolution::query()->create([
            'resolution_no' => '11',
            'resolution_title' => 'Blocked middle',
            'series' => 2026,
            'committee' => 'Rules',
            'created_by' => $user->id,
        ]);
        $allowedNewer = Resolution::query()->create([
            'resolution_no' => '12',
            'resolution_title' => 'Newer allowed',
            'series' => 2026,
            'committee' => $chairCommittee->name,
            'created_by' => $user->id,
        ]);

        AgendaItem::query()->create([
            'title' => 'Older agenda',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_DONE,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'resolution_id' => $allowedOlder->id,
            'created_by' => $user->id,
        ]);
        AgendaItem::query()->create([
            'title' => 'Newer agenda',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_DONE,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'resolution_id' => $allowedNewer->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('resolutions.show', $allowedNewer))
            ->assertOk()
            ->assertSee(route('resolutions.show', $allowedOlder), false)
            ->assertDontSee(route('resolutions.show', $blocked), false);
    }

    public function test_board_member_cannot_browse_appropriation_ordinance_archive(): void
    {
        [$user] = $this->linkedChair();

        $this->actingAs($user)
            ->get(route('appropriation-ordinances.index'))
            ->assertForbidden();
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
