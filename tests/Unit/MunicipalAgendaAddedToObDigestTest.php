<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Mail\SystemNotificationMail;
use App\Models\AgendaItem;
use App\Models\AgendaObPlacement;
use App\Models\LegislativeSession;
use App\Models\Municipality;
use App\Models\ObDocument;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\EmailNotificationSettings;
use App\Services\MunicipalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MunicipalAgendaAddedToObDigestTest extends TestCase
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

        [$user, $session, $sender] = $this->municipalUserWithFinalScheduledSession();

        $agendas = collect([
            $this->agendaForSender($sender, $user->id, '350', 'First'),
            $this->agendaForSender($sender, $user->id, '349', 'Second'),
            $this->agendaForSender($sender, $user->id, '351', 'Third'),
        ]);

        app(MunicipalNotifier::class)->notifyAgendasAddedToOb($agendas, $session, 'unfinished');

        $this->assertSame(1, UserNotification::query()
            ->where('user_id', $user->id)
            ->where('legislative_session_id', $session->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->count());

        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->first();

        $this->assertSame('Your request was added to the Order of Business', $notification->title);
        $this->assertStringContainsString($session->displayTitle(), $notification->body);
        $this->assertStringContainsString('#349 — A. Unfinished Business', $notification->body);
        $this->assertStringContainsString('#350 — A. Unfinished Business', $notification->body);
        $this->assertStringContainsString('#351 — A. Unfinished Business', $notification->body);

        Mail::assertSent(SystemNotificationMail::class, 1);
    }

    public function test_committee_reports_section_highlights_committee_report(): void
    {
        Mail::fake();

        [$user, $session, $sender] = $this->municipalUserWithFinalScheduledSession();
        $agenda = $this->agendaForSender($sender, $user->id, '270', 'With CR');

        app(MunicipalNotifier::class)->notifyAgendasAddedToOb([$agenda], $session, 'committee_reports');

        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('Your request was added under Committee Reports', $notification->title);
        $this->assertSame(
            '#270 — IV. Committee Reports (with committee report) was added to '.$session->displayTitle().'.',
            $notification->body
        );
        Mail::assertSent(SystemNotificationMail::class, 1);
    }

    public function test_resolves_section_from_placement_when_not_passed(): void
    {
        Mail::fake();

        [$user, $session, $sender] = $this->municipalUserWithFinalScheduledSession();
        $agenda = $this->agendaForSender($sender, $user->id, '271', 'From placement');

        $block = \App\Models\ObBlock::query()->create([
            'ob_document_id' => $session->obDocument->id,
            'type' => \App\Enums\ObBlockType::ReadingAgenda,
            'sort_order' => 1,
            'content' => ['text' => 'Agenda'],
            'agenda_item_id' => $agenda->id,
        ]);

        AgendaObPlacement::query()->create([
            'agenda_item_id' => $agenda->id,
            'ob_block_id' => $block->id,
            'legislative_session_id' => $session->id,
            'ob_document_id' => $session->obDocument->id,
            'section' => 'business_2nd',
            'section_label' => '1. Measures for 2nd Reading',
        ]);

        app(MunicipalNotifier::class)->notifyAgendasAddedToOb([$agenda], $session);

        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame(
            '#271 — 1. Measures for 2nd Reading was added to '.$session->displayTitle().'.',
            $notification->body
        );
    }

    public function test_later_batch_creates_a_separate_notification(): void
    {
        Mail::fake();

        [$user, $session, $sender] = $this->municipalUserWithFinalScheduledSession();
        $notifier = app(MunicipalNotifier::class);

        $notifier->notifyAgendasAddedToOb([
            $this->agendaForSender($sender, $user->id, '349', 'A'),
            $this->agendaForSender($sender, $user->id, '350', 'B'),
        ], $session, 'unfinished');

        $notifier->notifyAgendasAddedToOb([
            $this->agendaForSender($sender, $user->id, '351', 'C'),
        ], $session, 'committee_reports');

        $notifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $notifications);
        $this->assertStringContainsString('#349 — A. Unfinished Business', $notifications[0]->body);
        $this->assertStringContainsString('#350 — A. Unfinished Business', $notifications[0]->body);
        $this->assertSame('Your request was added under Committee Reports', $notifications[1]->title);
        $this->assertSame(
            '#351 — IV. Committee Reports (with committee report) was added to '.$session->displayTitle().'.',
            $notifications[1]->body
        );
        $this->assertNull($notifications[1]->read_at);

        Mail::assertSent(SystemNotificationMail::class, 2);
    }

    public function test_skips_when_session_is_not_scheduled(): void
    {
        Mail::fake();

        [$user, , $sender] = $this->municipalUserWithFinalScheduledSession();
        $session = LegislativeSession::query()->create([
            'session_number' => '54th',
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

        app(MunicipalNotifier::class)->notifyAgendasAddedToOb([
            $this->agendaForSender($sender, $user->id, '400', 'Skip'),
        ], $session->fresh('obDocument'), 'unfinished');

        $this->assertSame(0, UserNotification::query()
            ->where('user_id', $user->id)
            ->where('legislative_session_id', $session->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->count());
        Mail::assertNothingSent();
    }

    public function test_re_finalize_style_batch_skips_already_notified_agendas(): void
    {
        Mail::fake();

        [$user, $session, $sender] = $this->municipalUserWithFinalScheduledSession();
        $notifier = app(MunicipalNotifier::class);

        $first = $this->agendaForSender($sender, $user->id, '349', 'A');
        $second = $this->agendaForSender($sender, $user->id, '350', 'B');
        $third = $this->agendaForSender($sender, $user->id, '351', 'C');

        $notifier->notifyAgendasAddedToOb([$first, $second], $session, 'unfinished');

        $firstNotification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->first();
        $this->assertNotNull($firstNotification);
        $firstNotification->forceFill(['read_at' => now()])->save();

        $notifier->notifyAgendasAddedToOb([$first, $second, $third], $session, 'unfinished');

        $notifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $notifications);
        $this->assertNotNull($notifications[0]->fresh()->read_at);
        $this->assertStringContainsString('#349 — A. Unfinished Business', $notifications[0]->body);
        $this->assertSame(
            '#351 — A. Unfinished Business was added to '.$session->displayTitle().'.',
            $notifications[1]->body
        );
        Mail::assertSent(SystemNotificationMail::class, 2);
    }

    /** @return array{0: User, 1: LegislativeSession, 2: string} */
    protected function municipalUserWithFinalScheduledSession(): array
    {
        $municipality = Municipality::query()->create([
            'code' => 201,
            'description' => 'Orani',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::MunicipalViewer,
            'username' => 'muni_digest_'.uniqid(),
            'email' => 'muni_digest_'.uniqid().'@example.com',
            'is_active' => true,
            'municipality_id' => $municipality->id,
        ]);
        $user->load('municipality');
        $sender = $municipality->senderLabel();

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

        return [$user, $session->fresh('obDocument'), $sender];
    }

    protected function agendaForSender(string $sender, int $createdBy, string $trackingNo, string $title): AgendaItem
    {
        return AgendaItem::query()->create([
            'tracking_no' => $trackingNo,
            'title' => $title,
            'sender' => $sender,
            'status' => AgendaItem::STATUS_PENDING,
            'date_of_referral' => now()->toDateString(),
            'prescribed_days' => 0,
            'created_by' => $createdBy,
        ]);
    }
}
