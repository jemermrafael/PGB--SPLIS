<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\ObBlockType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeReferralDelivery;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\ObBlock;
use App\Models\ObDocument;
use App\Models\ScheduledCommitteeReferral;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CommitteeReferralScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledCommitteeReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_encoder_can_schedule_and_dispatch_referrals_to_chair_only(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        [$chairUser, $memberUser, $committee, $session, $agenda] = $this->sessionWithRegularUnassigned();

        $this->actingAs($encoder)
            ->get(route('scheduled-committee-referrals.index'))
            ->assertOk()
            ->assertSee('Schedule Committee Referral');

        $this->actingAs($encoder)
            ->post(route('scheduled-committee-referrals.store'), [
                'legislative_session_id' => $session->id,
                'scheduled_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'notes' => 'After OB',
            ])
            ->assertRedirect(route('scheduled-committee-referrals.index'));

        $schedule = ScheduledCommitteeReferral::query()->first();
        $this->assertNotNull($schedule);
        $this->assertSame(ScheduledCommitteeReferral::STATUS_SENT, $schedule->status);
        $this->assertNotNull($schedule->sent_at);

        $this->assertDatabaseHas('committee_referral_deliveries', [
            'scheduled_committee_referral_id' => $schedule->id,
            'agenda_item_id' => $agenda->id,
            'board_member_id' => $chairUser->board_member_id,
            'committee_id' => $committee->id,
        ]);

        $this->assertDatabaseMissing('committee_referral_deliveries', [
            'board_member_id' => $memberUser->board_member_id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $chairUser->id,
            'type' => UserNotification::TYPE_COMMITTEE_REFERRAL,
            'agenda_item_id' => $agenda->id,
        ]);

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $memberUser->id,
            'type' => UserNotification::TYPE_COMMITTEE_REFERRAL,
            'agenda_item_id' => $agenda->id,
        ]);
    }

    public function test_board_member_incoming_shows_two_hours_after_session_for_chair_only(): void
    {
        [$chairUser, $memberUser, $committee, $session, $agenda] = $this->sessionWithRegularUnassigned([
            'session_date' => now()->toDateString(),
            'session_time' => now()->subHour()->format('H:i:s'),
            'status' => 'scheduled',
        ]);

        $referrals = app(CommitteeReferralScheduleService::class);

        // Within 2 hours of session start — still locked (even though already on My Agenda / next OB).
        $this->assertFalse($session->fresh()->committeeReferralsAreAvailable());
        $this->assertTrue($referrals->referredFromLastObForChair($chairUser)['agendas']->isEmpty());

        $this->actingAs($chairUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Agendas Referred from last OB')
            ->assertSee('No referred agendas yet. Items appear 2 hours after the session.');

        $session->forceFill([
            'session_time' => now()->subHours(3)->format('H:i:s'),
        ])->save();

        $unlocked = $referrals->referredFromLastObForChair($chairUser);
        $this->assertTrue($session->fresh()->committeeReferralsAreAvailable());
        $this->assertTrue($unlocked['agendas']->contains(fn (AgendaItem $item) => $item->id === $agenda->id));
        $this->assertSame($session->id, $unlocked['session']?->id);

        $this->actingAs($chairUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Agendas Referred from last OB')
            ->assertSee($agenda->title)
            ->assertDontSee('No referred agendas yet. Items appear 2 hours after the session.');

        $this->assertTrue($referrals->referredFromLastObForChair($memberUser)['agendas']->isEmpty());
    }

    public function test_dispatch_due_command_sends_pending_schedules(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        [, , , $session, $agenda] = $this->sessionWithRegularUnassigned();

        $schedule = ScheduledCommitteeReferral::query()->create([
            'legislative_session_id' => $session->id,
            'scheduled_at' => now()->subMinutes(5),
            'status' => ScheduledCommitteeReferral::STATUS_PENDING,
            'created_by' => $encoder->id,
        ]);

        $this->artisan('splis:dispatch-scheduled-committee-referrals')
            ->assertSuccessful();

        $schedule->refresh();
        $this->assertSame(ScheduledCommitteeReferral::STATUS_SENT, $schedule->status);
        $this->assertTrue(CommitteeReferralDelivery::query()->where('agenda_item_id', $agenda->id)->exists());
    }

    public function test_board_member_cannot_access_schedule_pages(): void
    {
        [$chairUser] = $this->sessionWithRegularUnassigned();

        $this->actingAs($chairUser)
            ->get(route('scheduled-committee-referrals.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $sessionOverrides
     * @return array{0: User, 1: User, 2: Committee, 3: LegislativeSession, 4: AgendaItem}
     */
    protected function sessionWithRegularUnassigned(array $sessionOverrides = []): array
    {
        $term = CommitteeTerm::query()->current()->first()
            ?? CommitteeTerm::query()->create([
                'label' => '2025–2028',
                'year_from' => 2025,
                'year_to' => 2028,
                'is_current' => true,
            ]);

        if (! $term->is_current) {
            CommitteeTerm::query()->where('is_current', true)->update(['is_current' => false]);
            $term->forceFill(['is_current' => true])->save();
        }

        $chairBm = BoardMember::query()->create([
            'name' => 'Chair Person',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $memberBm = BoardMember::query()->create([
            'name' => 'Committee Member',
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
            'board_member_id' => $chairBm->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Chair,
            'sort_order' => 0,
        ]);

        CommitteeMembership::query()->create([
            'committee_id' => $committee->id,
            'board_member_id' => $memberBm->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Member,
            'sort_order' => 1,
        ]);

        $chairUser = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $chairBm->id,
            'username' => 'bm_chair_'.uniqid(),
            'is_active' => true,
            'name' => 'Hon. Chair Person',
        ]);

        $memberUser = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $memberBm->id,
            'username' => 'bm_member_'.uniqid(),
            'is_active' => true,
            'name' => 'Hon. Committee Member',
        ]);

        $encoderId = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true])->id;

        $agenda = AgendaItem::query()->create([
            'tracking_no' => '501',
            'title' => 'Unassigned housing referral agenda',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $encoderId,
        ]);

        $session = LegislativeSession::query()->create(array_merge([
            'session_number' => '12',
            'session_kind' => 'regular',
            'session_date' => now()->subDay()->toDateString(),
            'session_time' => '10:00:00',
            'status' => 'completed',
            'created_by' => $encoderId,
        ], $sessionOverrides));

        $document = ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_FINAL,
            'created_by' => $encoderId,
        ]);

        ObBlock::query()->create([
            'ob_document_id' => $document->id,
            'type' => ObBlockType::UnassignedAgenda,
            'sort_order' => 100,
            'content' => ['title' => $agenda->title, 'kind' => 'regular'],
            'agenda_item_id' => $agenda->id,
        ]);

        return [$chairUser, $memberUser, $committee, $session->fresh('obDocument.blocks.agendaItem'), $agenda];
    }
}
