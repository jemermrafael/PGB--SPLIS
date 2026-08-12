<?php

namespace Tests\Unit;

use App\Enums\CommitteeMembershipRole;
use App\Enums\UserRole;
use App\Mail\SystemNotificationMail;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\BoardMemberNotifier;
use App\Services\EmailNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BoardMemberAgendaAddedToObDigestTest extends TestCase
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

    public function test_multiple_agendas_in_one_batch_send_one_combined_notification(): void
    {
        Mail::fake();

        [$user, $committee, $session] = $this->boardMemberWithFinalScheduledSession();

        $agendas = collect([
            $this->agendaForCommittee($committee->name, $user->id, '350', 'First'),
            $this->agendaForCommittee($committee->name, $user->id, '349', 'Second'),
            $this->agendaForCommittee($committee->name, $user->id, '351', 'Third'),
        ]);

        app(BoardMemberNotifier::class)->notifyAgendasAddedToOb($agendas, $session);

        $this->assertSame(1, UserNotification::query()
            ->where('user_id', $user->id)
            ->where('legislative_session_id', $session->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->count());

        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->first();

        $this->assertSame('Agenda added to Order of Business', $notification->title);
        $this->assertSame(
            '#349, #350, #351 was added to '.$session->displayTitle().'.',
            $notification->body
        );

        Mail::assertSent(SystemNotificationMail::class, 1);
    }

    public function test_later_batch_creates_a_separate_notification(): void
    {
        Mail::fake();

        [$user, $committee, $session] = $this->boardMemberWithFinalScheduledSession();
        $notifier = app(BoardMemberNotifier::class);

        $notifier->notifyAgendasAddedToOb([
            $this->agendaForCommittee($committee->name, $user->id, '349', 'A'),
            $this->agendaForCommittee($committee->name, $user->id, '350', 'B'),
        ], $session);

        $notifier->notifyAgendasAddedToOb([
            $this->agendaForCommittee($committee->name, $user->id, '351', 'C'),
        ], $session);

        $notifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $notifications);
        $this->assertSame(
            '#349, #350 was added to '.$session->displayTitle().'.',
            $notifications[0]->body
        );
        $this->assertSame(
            '#351 was added to '.$session->displayTitle().'.',
            $notifications[1]->body
        );
        $this->assertNull($notifications[1]->read_at);

        Mail::assertSent(SystemNotificationMail::class, 2);
    }

    public function test_repeat_same_batch_does_not_duplicate(): void
    {
        Mail::fake();

        [$user, $committee, $session] = $this->boardMemberWithFinalScheduledSession();
        $agendas = collect([
            $this->agendaForCommittee($committee->name, $user->id, '201', 'Alpha'),
            $this->agendaForCommittee($committee->name, $user->id, '202', 'Beta'),
        ]);

        $notifier = app(BoardMemberNotifier::class);
        $notifier->notifyAgendasAddedToOb($agendas, $session);
        $notifier->notifyAgendasAddedToOb($agendas, $session);

        Mail::assertSent(SystemNotificationMail::class, 1);
        $this->assertSame(1, UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->count());
    }

    public function test_re_finalize_style_batch_skips_already_notified_agendas(): void
    {
        Mail::fake();

        [$user, $committee, $session] = $this->boardMemberWithFinalScheduledSession();
        $notifier = app(BoardMemberNotifier::class);

        $first = $this->agendaForCommittee($committee->name, $user->id, '349', 'A');
        $second = $this->agendaForCommittee($committee->name, $user->id, '350', 'B');
        $third = $this->agendaForCommittee($committee->name, $user->id, '351', 'C');

        $notifier->notifyAgendasAddedToOb([$first, $second], $session);

        $firstNotification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->first();
        $this->assertNotNull($firstNotification);
        $firstNotification->forceFill(['read_at' => now()])->save();

        // Same path as draft→final again: pass all linked agendas, including ones already notified.
        $notifier->notifyAgendasAddedToOb([$first, $second, $third], $session);

        $notifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $notifications);
        $this->assertNotNull($notifications[0]->fresh()->read_at, 'Prior digest must not be deleted on re-notify');
        $this->assertSame(
            '#349, #350 was added to '.$session->displayTitle().'.',
            $notifications[0]->body
        );
        $this->assertSame(
            '#351 was added to '.$session->displayTitle().'.',
            $notifications[1]->body
        );

        Mail::assertSent(SystemNotificationMail::class, 2);
    }

    public function test_skips_when_session_is_not_scheduled_or_ob_not_final(): void
    {
        Mail::fake();

        [$user, $committee] = $this->boardMemberOnCommittee();
        $session = LegislativeSession::query()->create([
            'session_number' => '53rd',
            'session_kind' => 'regular',
            'session_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_FINAL,
            'created_by' => $user->id,
        ]);

        app(BoardMemberNotifier::class)->notifyAgendasAddedToOb([
            $this->agendaForCommittee($committee->name, $user->id, '400', 'Skip me'),
        ], $session->fresh('obDocument'));

        $this->assertSame(0, UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->count());
        Mail::assertNothingSent();
    }

    public function test_immediate_committee_referral_alerts_are_disabled_for_board_members(): void
    {
        Mail::fake();

        [$chairUser, $committee] = $this->boardMemberOnCommittee();

        $term = CommitteeTerm::query()->current()->first();
        $memberProfile = BoardMember::query()->create([
            'name' => 'Regular Member',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);
        CommitteeMembership::query()->create([
            'committee_id' => $committee->id,
            'board_member_id' => $memberProfile->id,
            'committee_term_id' => $term->id,
            'role' => CommitteeMembershipRole::Member,
            'sort_order' => 1,
        ]);
        $memberUser = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $memberProfile->id,
            'username' => 'bm_member_'.uniqid(),
            'email' => 'bm_member_'.uniqid().'@example.com',
            'is_active' => true,
            'name' => 'Hon. Regular Member',
        ]);

        $agenda = $this->agendaForCommittee($committee->name, $chairUser->id, '401', 'Chair-only notice');

        app(BoardMemberNotifier::class)->notifyCommitteeReferral($agenda);

        $this->assertSame(
            0,
            UserNotification::query()
                ->whereIn('user_id', [$chairUser->id, $memberUser->id])
                ->where('type', UserNotification::TYPE_COMMITTEE_REFERRAL)
                ->count()
        );
        Mail::assertNothingSent();
    }

    protected function boardMemberWithFinalScheduledSession(): array
    {
        [$user, $committee] = $this->boardMemberOnCommittee();

        $session = LegislativeSession::query()->create([
            'session_number' => '53rd',
            'session_kind' => 'regular',
            'session_date' => '2026-08-03',
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        ObDocument::query()->create([
            'legislative_session_id' => $session->id,
            'title' => 'OB',
            'status' => ObDocument::STATUS_FINAL,
            'created_by' => $user->id,
        ]);

        return [$user, $committee, $session->fresh('obDocument')];
    }

    /** @return array{0: User, 1: Committee} */
    protected function boardMemberOnCommittee(): array
    {
        $term = CommitteeTerm::query()->current()->first()
            ?? CommitteeTerm::query()->create([
                'label' => '2025-2028',
                'year_from' => 2025,
                'year_to' => 2028,
                'is_current' => true,
            ]);

        $boardMember = BoardMember::query()->create([
            'name' => 'Digest Member',
            'honorific' => 'Hon.',
            'is_active' => true,
        ]);

        $committee = Committee::query()->create([
            'name' => 'Peace and Order and Public Safety',
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
            'username' => 'bm_digest_'.uniqid(),
            'email' => 'bm_digest_'.uniqid().'@example.com',
            'is_active' => true,
            'name' => 'Hon. Digest Member',
        ]);

        return [$user, $committee];
    }

    protected function agendaForCommittee(string $committeeName, int $createdBy, string $trackingNo, string $title): AgendaItem
    {
        return AgendaItem::query()->create([
            'tracking_no' => $trackingNo,
            'title' => $title,
            'committee_referred' => $committeeName,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $createdBy,
        ]);
    }
}
