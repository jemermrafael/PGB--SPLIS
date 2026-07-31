<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Mail\SystemNotificationMail;
use App\Models\AgendaItem;
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

        app(MunicipalNotifier::class)->notifyAgendasAddedToOb($agendas, $session);

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
        $this->assertSame(
            '#349, #350, #351 was added to '.$session->displayTitle().'.',
            $notification->body
        );

        Mail::assertSent(SystemNotificationMail::class, 1);
    }

    public function test_later_batch_creates_a_separate_notification(): void
    {
        Mail::fake();

        [$user, $session, $sender] = $this->municipalUserWithFinalScheduledSession();
        $notifier = app(MunicipalNotifier::class);

        $notifier->notifyAgendasAddedToOb([
            $this->agendaForSender($sender, $user->id, '349', 'A'),
            $this->agendaForSender($sender, $user->id, '350', 'B'),
        ], $session);

        $notifier->notifyAgendasAddedToOb([
            $this->agendaForSender($sender, $user->id, '351', 'C'),
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
        ], $session->fresh('obDocument'));

        $this->assertSame(0, UserNotification::query()
            ->where('user_id', $user->id)
            ->where('legislative_session_id', $session->id)
            ->where('type', UserNotification::TYPE_AGENDA_ADDED_TO_OB)
            ->count());
        Mail::assertNothingSent();
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
