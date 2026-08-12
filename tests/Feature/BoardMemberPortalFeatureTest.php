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
use App\Models\ObDocument;
use App\Models\SessionAttendance;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\BoardMemberNotifier;
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
            ->assertSee('Agendas Referred from last OB')
            ->assertDontSee('>Your Committees</')
            ->assertDontSee('Session Calendar');
    }

    public function test_board_member_pending_stats_include_no_due_date_agendas(): void
    {
        [$user, $chairCommittee] = $this->linkedBoardMemberWithCommittee();

        AgendaItem::query()->create([
            'title' => 'Strictly pending item',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'prescribed_days' => 14,
            'created_by' => $user->id,
        ]);

        AgendaItem::query()->create([
            'title' => 'No due date awaiting action',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_NO_DUE_DATE,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('board-member.agenda.search'))
            ->assertOk()
            ->assertJsonPath('stats.pending', 2);

        $this->actingAs($user)
            ->getJson(route('board-member.agenda.search', ['status' => AgendaItem::STATUS_PENDING]))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['title' => 'Strictly pending item'])
            ->assertJsonFragment(['title' => 'No due date awaiting action']);
    }

    public function test_board_member_my_agenda_search_lists_only_chairmanship_items(): void
    {
        [$user, $chairCommittee, $term, $boardMember] = $this->linkedBoardMemberWithCommittee();

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

        $chairAgenda = AgendaItem::query()->create([
            'title' => 'My Agenda chairmanship item',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $memberAgenda = AgendaItem::query()->create([
            'title' => 'My Agenda membership item',
            'committee_referred' => $memberCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('board-member.agenda.index'))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('board-member.agenda.search'))
            ->assertOk()
            ->assertJsonFragment(['title' => $chairAgenda->title])
            ->assertJsonMissing(['title' => $memberAgenda->title])
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user)
            ->get(route('board-member.agenda.search'))
            ->assertRedirect(route('board-member.agenda.index'));
    }

    public function test_board_member_dashboard_next_ob_lists_only_chairmanship_agendas(): void
    {
        [$user, $chairCommittee, $term, $boardMember] = $this->linkedBoardMemberWithCommittee();

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

        $chairAgenda = AgendaItem::query()->create([
            'title' => 'Chair item on next OB',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $memberAgenda = AgendaItem::query()->create([
            'title' => 'Member-only item on next OB',
            'committee_referred' => $memberCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::query()->create([
            'session_number' => 'Next Regular Session',
            'session_kind' => 'regular',
            'session_date' => now()->addDays(2)->toDateString(),
            'session_time' => '10:00:00',
            'venue' => 'Session Hall',
            'status' => 'scheduled',
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'Order of Business',
            'status' => ObDocument::STATUS_FINAL,
            'created_by' => $user->id,
        ]);

        foreach ([$chairAgenda, $memberAgenda] as $index => $agenda) {
            $document->blocks()->create([
                'type' => 'committee_report',
                'sort_order' => $index + 1,
                'agenda_item_id' => $agenda->id,
                'content' => ['title' => $agenda->title],
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Agendas on next OB')
            ->assertSee($chairAgenda->title)
            ->assertDontSee($memberAgenda->title);
    }

    public function test_board_member_can_update_profile(): void
    {
        [$user, , , $boardMember] = $this->linkedBoardMemberWithCommittee();

        $this->actingAs($user)
            ->get(route('board-member.profile.edit'))
            ->assertOk()
            ->assertSee('Notification Preferences')
            ->assertSee('Scheduled Committee Referral')
            ->assertSee('In-app')
            ->assertSee('Email');

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

    public function test_board_member_can_view_session_packet_and_attendance_status(): void
    {
        [$user, $committee] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'title' => 'Committee item for session packet',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $session = LegislativeSession::query()->create([
            'session_number' => 'Session 42',
            'session_kind' => 'regular',
            'session_date' => now()->addDays(2)->toDateString(),
            'session_time' => '09:00:00',
            'venue' => 'Session Hall',
            'status' => 'scheduled',
        ]);

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'Order of Business',
            'status' => ObDocument::STATUS_FINAL,
            'next_session_agenda_no' => 1,
            'created_by' => $user->id,
        ]);

        $document->blocks()->create([
            'type' => 'unassigned_agenda',
            'sort_order' => 1,
            'agenda_item_id' => $agenda->id,
            'content' => ['title' => $agenda->title],
        ]);

        SessionAttendance::query()->create([
            'legislative_session_id' => $session->id,
            'board_member_id' => $user->board_member_id,
            'is_present' => false,
            'remarks' => 'OB',
            'recorded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('board-member.sessions.show', $session))
            ->assertOk()
            ->assertSee('Session Details')
            ->assertSee('My Attendance')
            ->assertSee('Official Business')
            ->assertSee($agenda->displayLabel());

        $this->actingAs($user)
            ->get(route('board-member.sessions.index'))
            ->assertOk()
            ->assertSee($session->displayTitle());
    }

    public function test_board_member_sessions_index_hides_draft_and_lists_scheduled_without_final_ob(): void
    {
        [$user] = $this->linkedBoardMemberWithCommittee();

        $draft = LegislativeSession::query()->create([
            'session_number' => '53rd REGULAR SESSION',
            'session_kind' => 'regular',
            'session_date' => now()->addDays(3)->toDateString(),
            'status' => 'draft',
        ]);
        ObDocument::query()->create([
            'legislative_session_id' => $draft->id,
            'title' => 'Draft OB',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $scheduled = LegislativeSession::query()->create([
            'session_number' => '54th REGULAR SESSION',
            'session_kind' => 'regular',
            'session_date' => now()->addDays(5)->toDateString(),
            'status' => 'scheduled',
        ]);
        ObDocument::query()->create([
            'legislative_session_id' => $scheduled->id,
            'title' => 'Draft OB still',
            'status' => ObDocument::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('board-member.sessions.index'))
            ->assertOk()
            ->assertDontSee($draft->displayTitle())
            ->assertSee($scheduled->displayTitle())
            ->assertSee('Order of Business is still draft');
    }

    public function test_board_member_can_toggle_watchlist(): void
    {
        [$user] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'title' => 'Watch me',
            'status' => AgendaItem::STATUS_PENDING,
            'prescribed_days' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('board-member.watchlist.store'), [
                'watchable_type' => 'agenda',
                'watchable_id' => $agenda->id,
            ])
            ->assertSessionHas('status', 'Added to your watchlist.');

        $this->assertDatabaseHas('board_member_watchlist_items', [
            'user_id' => $user->id,
            'watchable_type' => AgendaItem::class,
            'watchable_id' => $agenda->id,
        ]);

        $this->actingAs($user)
            ->post(route('board-member.watchlist.store'), [
                'watchable_type' => 'agenda',
                'watchable_id' => $agenda->id,
            ])
            ->assertSessionHas('status', 'Removed from your watchlist.');

        $this->assertDatabaseMissing('board_member_watchlist_items', [
            'user_id' => $user->id,
            'watchable_type' => AgendaItem::class,
            'watchable_id' => $agenda->id,
        ]);
    }

    public function test_board_member_notification_preference_can_disable_in_app_creation(): void
    {
        [$user, $committee] = $this->linkedBoardMemberWithCommittee();

        $agenda = AgendaItem::query()->create([
            'title' => 'Published item',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_DONE,
            'date_of_referral' => now()->toDateString(),
            'date_passed' => now()->toDateString(),
            'prescribed_days' => 0,
            'resolution_id' => null,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->put(route('board-member.profile.notifications.update'), [
                'preferences' => [
                    'in_app' => [
                        UserNotification::TYPE_AGENDA_PUBLISHED => false,
                    ],
                    'email' => [
                        UserNotification::TYPE_AGENDA_PUBLISHED => true,
                    ],
                ],
            ])
            ->assertRedirect(route('board-member.profile.edit'));

        $agenda->forceFill([
            'resolution_title' => 'Resolution adopting standards',
            'reso_ord_ao_no' => '11',
            'reso_ord_ao_series' => (int) now()->format('Y'),
            'resolution_id' => \App\Models\Resolution::query()->create([
                'resolution_no' => '11',
                'resolution_title' => 'Resolution adopting standards',
                'series' => (int) now()->format('Y'),
                'status' => 'approved',
                'created_by' => $user->id,
            ])->id,
        ])->save();

        app(BoardMemberNotifier::class)->notifyAgendaPublished($agenda->fresh());

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_AGENDA_PUBLISHED,
            'agenda_item_id' => $agenda->id,
        ]);
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

    public function test_board_member_agenda_index_lists_all_committee_membership_items(): void
    {
        [$chairUser, $chairCommittee, $term, $boardMember] = $this->linkedBoardMemberWithCommittee();

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

        $chairAgenda = AgendaItem::query()->create([
            'title' => 'Chairmanship referral item',
            'committee_referred' => $chairCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $chairUser->id,
        ]);

        $memberAgenda = AgendaItem::query()->create([
            'title' => 'Membership-only referral item',
            'committee_referred' => $memberCommittee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'date_received' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $chairUser->id,
        ]);

        $this->actingAs($chairUser)
            ->get(route('agenda.index'))
            ->assertForbidden();

        $this->actingAs($chairUser)
            ->getJson(route('agenda.search'))
            ->assertForbidden();

        // My Agenda is chairmanship-scoped (not staff /agenda/search).
        $this->actingAs($chairUser)
            ->getJson(route('board-member.agenda.search'))
            ->assertOk()
            ->assertJsonFragment(['title' => $chairAgenda->title])
            ->assertJsonMissing(['title' => $memberAgenda->title])
            ->assertJsonPath('meta.total', 1);
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
