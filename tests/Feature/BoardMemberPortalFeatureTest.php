<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardMemberPortalFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_member_can_view_my_committees_with_roster_and_agendas(): void
    {
        [$user, $committee, $term] = $this->linkedBoardMemberWithCommittee();

        AgendaItem::query()->create([
            'title' => 'Referral for housing ordinance',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('board-member.committees.index'))
            ->assertOk()
            ->assertSee('My Committees')
            ->assertSee('Chairmanship')
            ->assertSee($committee->name);

        $this->actingAs($user)
            ->get(route('board-member.committees.show', ['committee' => $committee, 'term' => $term->id]))
            ->assertOk()
            ->assertSee('Committee roster')
            ->assertSee('Hon. Linked Member')
            ->assertSee('Other Member')
            ->assertSee('Referral for housing ordinance');
    }

    public function test_board_member_dashboard_shows_today_briefing(): void
    {
        [$user] = $this->linkedBoardMemberWithCommittee();

        LegislativeSession::query()->create([
            'session_number' => '1',
            'session_kind' => 'regular',
            'session_date' => now()->addDays(3)->toDateString(),
            'session_time' => '10:00:00',
            'venue' => 'Session Hall',
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Today’s briefing')
            ->assertSee('Next Session')
            ->assertSee('My Agendas on next OB')
            ->assertSee('Agenda deadlines within')
            ->assertDontSee('>Your Committees</')
            ->assertDontSee('Session Calendar');
    }

    public function test_board_member_can_update_profile(): void
    {
        [$user, , , $boardMember] = $this->linkedBoardMemberWithCommittee();

        $this->actingAs($user)
            ->put(route('board-member.profile.update'), [
                'name' => 'Hon. Updated Login',
                'username' => 'bm_updated',
                'email' => 'bm.updated@example.com',
                'honorific' => 'Hon.',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('board-member.profile.edit'));

        $user->refresh();
        $boardMember->refresh();

        $this->assertSame('Hon. Updated Login', $user->name);
        $this->assertSame('bm_updated', $user->username);
        $this->assertSame('bm.updated@example.com', $user->email);
        $this->assertSame('Hon.', $boardMember->honorific);
    }

    public function test_unlinked_board_member_sees_account_link_warning(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => null,
            'username' => 'bm_unlinked',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('not linked to a Board Member profile');

        $this->actingAs($user)
            ->get(route('board-member.committees.index'))
            ->assertOk()
            ->assertSee('not linked to a Board Member profile');
    }

    /**
     * @return array{0: User, 1: Committee, 2: CommitteeTerm, 3: BoardMember}
     */
    protected function linkedBoardMemberWithCommittee(): array
    {
        $term = CommitteeTerm::query()->create([
            'label' => '2025–2028',
            'year_from' => 2025,
            'year_to' => 2028,
            'is_current' => true,
        ]);

        $boardMember = BoardMember::query()->create([
            'name' => 'Linked Member',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $other = BoardMember::query()->create([
            'name' => 'Other Member',
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

        CommitteeMembership::query()->create([
            'committee_id' => $committee->id,
            'board_member_id' => $other->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Member,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $boardMember->id,
            'username' => 'bm_linked',
            'is_active' => true,
            'name' => 'Hon. Linked Member',
        ]);

        return [$user, $committee, $term, $boardMember];
    }
}
