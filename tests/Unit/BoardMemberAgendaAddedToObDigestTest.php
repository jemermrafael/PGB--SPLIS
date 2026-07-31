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

    public function test_multiple_agendas_on_same_ob_send_one_email_and_one_notification(): void
    {
        Mail::fake();

        [$user, $committee] = $this->boardMemberOnCommittee();
        $session = LegislativeSession::query()->create([
            'session_number' => '53rd',
            'session_kind' => 'regular',
            'session_date' => now()->toDateString(),
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

        $agendas = collect([
            $this->agendaForCommittee($committee->name, $user->id, '101', 'First agenda'),
            $this->agendaForCommittee($committee->name, $user->id, '102', 'Second agenda'),
            $this->agendaForCommittee($committee->name, $user->id, '103', 'Third agenda'),
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

        $this->assertNull($notification->agenda_item_id);
        $this->assertSame('Agendas added to Order of Business', $notification->title);
        $this->assertStringContainsString('3 agendas were added', $notification->body);
        $this->assertStringContainsString('#101', $notification->body);
        $this->assertStringContainsString('#102', $notification->body);
        $this->assertStringContainsString('#103', $notification->body);

        Mail::assertSent(SystemNotificationMail::class, 1);
    }

    public function test_repeat_same_digest_does_not_resend_email(): void
    {
        Mail::fake();

        [$user, $committee] = $this->boardMemberOnCommittee();
        $session = LegislativeSession::query()->create([
            'session_number' => '53rd',
            'session_kind' => 'regular',
            'session_date' => now()->toDateString(),
            'status' => 'scheduled',
            'created_by' => $user->id,
        ]);

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
