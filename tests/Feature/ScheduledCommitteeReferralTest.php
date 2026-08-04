<?php

namespace Tests\Feature;

use App\Enums\CommitteeMembershipRole;
use App\Enums\ObBlockType;
use App\Enums\UserRole;
use App\Mail\SystemNotificationMail;
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
use App\Services\EmailNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduledCommitteeReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $path = app(EmailNotificationSettings::class)->path();
        if (is_file($path)) {
            @unlink($path);
        }

        parent::tearDown();
    }

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
            'type' => UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL,
            'agenda_item_id' => $agenda->id,
        ]);

        $this->assertSame(
            1,
            UserNotification::query()
                ->where('user_id', $chairUser->id)
                ->where('type', UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL)
                ->count(),
        );

        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $memberUser->id,
            'type' => UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL,
        ]);

        $this->assertFalse((bool) $schedule->send_email);
    }

    public function test_dispatch_joins_multiple_agendas_into_one_notification_per_chair(): void
    {
        Mail::fake();

        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        [$chairUser, , $committee, $session, $agenda] = $this->sessionWithRegularUnassigned();
        $chairUser->forceFill(['email' => 'chair@example.com'])->save();

        $second = AgendaItem::query()->create([
            'tracking_no' => '502',
            'title' => 'Second unassigned housing agenda',
            'committee_referred' => $committee->name,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $encoder->id,
        ]);

        ObBlock::query()->create([
            'ob_document_id' => $session->obDocument->id,
            'type' => ObBlockType::UnassignedAgenda,
            'sort_order' => 101,
            'content' => ['title' => $second->title, 'kind' => 'regular'],
            'agenda_item_id' => $second->id,
        ]);

        app(EmailNotificationSettings::class)->update([
            'enabled' => true,
            'types' => [
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER => [
                    UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL => true,
                ],
            ],
        ]);

        $this->actingAs($encoder)
            ->post(route('scheduled-committee-referrals.store'), [
                'legislative_session_id' => $session->id,
                'scheduled_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'send_email' => '1',
            ])
            ->assertRedirect(route('scheduled-committee-referrals.index'));

        $notifications = UserNotification::query()
            ->where('user_id', $chairUser->id)
            ->where('type', UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL)
            ->get();

        $this->assertCount(1, $notifications);
        $this->assertNull($notifications->first()->agenda_item_id);
        $this->assertSame($session->id, $notifications->first()->legislative_session_id);
        $this->assertStringContainsString($agenda->displayLabel(), (string) $notifications->first()->body);
        $this->assertStringContainsString($second->displayLabel(), (string) $notifications->first()->body);

        Mail::assertSent(SystemNotificationMail::class, 1);
        Mail::assertSent(SystemNotificationMail::class, function (SystemNotificationMail $mail) use ($chairUser, $agenda, $second) {
            return $mail->hasTo($chairUser->email)
                && $mail->notificationTitle === 'Incoming agendas for referral'
                && str_contains($mail->notificationBody, $agenda->displayLabel())
                && str_contains($mail->notificationBody, $second->displayLabel());
        });
    }

    public function test_encoder_can_schedule_with_email_to_chair(): void
    {
        Mail::fake();

        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        [$chairUser, , , $session, $agenda] = $this->sessionWithRegularUnassigned();
        $chairUser->forceFill(['email' => 'chair@example.com'])->save();

        app(EmailNotificationSettings::class)->update([
            'enabled' => true,
            'types' => [
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER => [
                    UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL => true,
                ],
            ],
        ]);

        $this->actingAs($encoder)
            ->post(route('scheduled-committee-referrals.store'), [
                'legislative_session_id' => $session->id,
                'scheduled_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'send_email' => '1',
            ])
            ->assertRedirect(route('scheduled-committee-referrals.index'));

        $schedule = ScheduledCommitteeReferral::query()->first();
        $this->assertNotNull($schedule);
        $this->assertTrue((bool) $schedule->send_email);
        $this->assertSame(ScheduledCommitteeReferral::STATUS_SENT, $schedule->status);

        Mail::assertSent(SystemNotificationMail::class, function (SystemNotificationMail $mail) use ($chairUser, $agenda) {
            return $mail->hasTo($chairUser->email)
                && $mail->notificationTitle === 'Incoming agenda for referral'
                && str_contains($mail->notificationBody, $agenda->displayLabel());
        });
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

    public function test_admin_can_soft_delete_schedule_but_encoder_cannot(): void
    {
        $encoder = User::factory()->create(['role' => UserRole::Encoder, 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        [, , , $session] = $this->sessionWithRegularUnassigned();

        $schedule = ScheduledCommitteeReferral::query()->create([
            'legislative_session_id' => $session->id,
            'scheduled_at' => now()->addDay(),
            'status' => ScheduledCommitteeReferral::STATUS_PENDING,
            'created_by' => $encoder->id,
            'send_email' => false,
        ]);

        $this->actingAs($encoder)
            ->delete(route('scheduled-committee-referrals.destroy', $schedule))
            ->assertForbidden();

        $this->assertNull($schedule->fresh()->deleted_at);

        $this->actingAs($admin)
            ->delete(route('scheduled-committee-referrals.destroy', $schedule))
            ->assertRedirect(route('scheduled-committee-referrals.index'));

        $this->assertSoftDeleted('scheduled_committee_referrals', ['id' => $schedule->id]);
        $this->assertNull(
            ScheduledCommitteeReferral::query()->find($schedule->id),
        );
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
