<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\SystemNotificationMail;
use App\Models\BoardMember;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\EmailNotificationService;
use App\Services\EmailNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
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

    public function test_admin_can_open_and_save_email_notification_settings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.email-notifications.index', ['tab' => 'board_member']))
            ->assertOk()
            ->assertSee('Email Notifications', false)
            ->assertSee('Board Members', false)
            ->assertSee('Municipal Accounts', false)
            ->assertSee('Committee Referral', false)
            ->assertSee('Agenda was published to Resolution', false)
            ->assertSee('New Ordinance published', false)
            ->assertSee('New Appropriation Ordinance published', false)
            ->assertSee('Activity log events', false)
            ->assertSee('Use Gmail preset', false)
            ->assertSee('SMTP', false)
            ->assertSee('data-email-rich-editor', false)
            ->assertSee('data-email-preview', false)
            ->assertSee('email-template-preview-modal', false)
            ->assertSee('Body formatting', false)
            ->assertSee('Legislative Information System', false)
            ->assertSee('Sangguniang Panlalawigan', false)
            ->assertSee('splis-email-message-card', false);

        $defaults = app(EmailNotificationSettings::class)->get();
        $this->assertTrue($defaults['types'][EmailNotificationSettings::AUDIENCE_BOARD_MEMBER][UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL]);
        $this->assertFalse($defaults['types'][EmailNotificationSettings::AUDIENCE_BOARD_MEMBER][EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED]);
        $this->assertFalse($defaults['types'][EmailNotificationSettings::AUDIENCE_MUNICIPAL][EmailNotificationSettings::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED]);
        $this->assertFalse($defaults['types'][EmailNotificationSettings::AUDIENCE_STAFF][UserNotification::TYPE_ACTIVITY_LOG]);
        $this->assertTrue($defaults['types'][EmailNotificationSettings::AUDIENCE_STAFF][EmailNotificationSettings::TYPE_COMMITTEE_REPORT_SUBMITTED]);
        $this->assertTrue($defaults['types'][EmailNotificationSettings::AUDIENCE_BOARD_MEMBER][UserNotification::TYPE_COMMITTEE_REPORT_SUBMITTED]);
        $this->assertSame(
            'Committee Report submitted for your Committee',
            $defaults['templates'][EmailNotificationSettings::AUDIENCE_BOARD_MEMBER][UserNotification::TYPE_COMMITTEE_REPORT_SUBMITTED]['subject'],
        );
        $this->assertStringContainsString(
            '{{submitter_name}}',
            $defaults['templates'][EmailNotificationSettings::AUDIENCE_BOARD_MEMBER][UserNotification::TYPE_COMMITTEE_REPORT_SUBMITTED]['body'],
        );

        $this->actingAs($admin)
            ->put(route('admin.email-notifications.update'), [
                'enabled' => '1',
                'active_tab' => EmailNotificationSettings::AUDIENCE_MUNICIPAL,
                'types' => [
                    EmailNotificationSettings::AUDIENCE_BOARD_MEMBER => [
                        UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL => '1',
                    ],
                    EmailNotificationSettings::AUDIENCE_MUNICIPAL => [
                        EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED => '1',
                        EmailNotificationSettings::TYPE_ORDINANCE_PUBLISHED => '1',
                    ],
                    EmailNotificationSettings::AUDIENCE_STAFF => [
                        EmailNotificationSettings::TYPE_COMMITTEE_REPORT_SUBMITTED => '1',
                    ],
                ],
                'templates' => [
                    EmailNotificationSettings::AUDIENCE_MUNICIPAL => [
                        EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED => [
                            'subject' => 'Custom resolution subject',
                            'body' => 'Custom body for {{label}}',
                            'action_label' => 'Open request',
                        ],
                    ],
                ],
                'branding' => [
                    'sign_off' => 'Best regards,',
                    'signature' => 'PGB - SPLIS Office',
                    'header_eyebrow' => 'Legislative Information System',
                    'header_title' => 'Sangguniang Panlalawigan',
                ],
                'smtp' => [
                    'mailer' => 'log',
                    'host' => 'smtp.example.com',
                    'port' => 587,
                    'username' => 'splis',
                    'password' => 'secret',
                    'encryption' => 'tls',
                    'from_address' => 'noreply@bataan.gov.ph',
                    'from_name' => 'SPLIS',
                ],
            ])
            ->assertRedirect(route('admin.email-notifications.index', [
                'tab' => EmailNotificationSettings::AUDIENCE_MUNICIPAL,
            ]));

        $settings = app(EmailNotificationSettings::class)->get();
        $this->assertTrue($settings['enabled']);
        $this->assertTrue($settings['types'][EmailNotificationSettings::AUDIENCE_BOARD_MEMBER][UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL]);
        $this->assertFalse($settings['types'][EmailNotificationSettings::AUDIENCE_BOARD_MEMBER][UserNotification::TYPE_AGENDA_PUBLISHED]);
        $this->assertTrue($settings['types'][EmailNotificationSettings::AUDIENCE_MUNICIPAL][EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED]);
        $this->assertSame('Custom resolution subject', $settings['templates'][EmailNotificationSettings::AUDIENCE_MUNICIPAL][EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED]['subject']);
        $this->assertSame('Best regards,', $settings['branding']['sign_off']);
        $this->assertSame('PGB - SPLIS Office', $settings['branding']['signature']);
        $this->assertSame('smtp.example.com', $settings['smtp']['host']);
        $this->assertSame('secret', $settings['smtp']['password']);
    }

    public function test_encoder_cannot_open_email_notification_settings(): void
    {
        $encoder = User::factory()->create([
            'role' => UserRole::Encoder,
            'is_active' => true,
        ]);

        $this->actingAs($encoder)
            ->get(route('admin.email-notifications.index'))
            ->assertForbidden();
    }

    public function test_board_member_scheduled_referral_sends_templated_email_when_enabled(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'email' => 'bm@example.com',
            'is_active' => true,
        ]);

        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL,
            'title' => 'Incoming agenda for referral',
            'body' => 'Test body',
            'link' => '/agenda/1',
        ]);

        app(EmailNotificationService::class)->sendForNotification(
            $user,
            $notification,
            EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
            force: true,
            vars: [
                'email_subject' => 'Incoming agenda for referral',
                'summary' => 'Test body',
            ],
        );

        Mail::assertSent(SystemNotificationMail::class, function (SystemNotificationMail $mail) use ($user) {
            $html = $mail->render();

            return $mail->hasTo($user->email)
                && $mail->notificationTitle === 'Incoming agenda for referral'
                && str_contains($mail->notificationBody, 'Test body')
                && str_contains($html, 'Legislative Information System')
                && str_contains($html, 'Sangguniang Panlalawigan')
                && str_contains($html, 'bataan-seal.png')
                && str_contains($html, 'dashboard-hero-bg.png')
                && str_contains($html, 'Thanks,')
                && str_contains($html, config('app.name'));
        });
    }

    public function test_email_branding_settings_are_used_in_outgoing_mail(): void
    {
        Mail::fake();

        app(EmailNotificationSettings::class)->update([
            'enabled' => true,
            'branding' => [
                'sign_off' => 'Respectfully,',
                'signature' => 'SPLIS Admin Office',
                'header_eyebrow' => 'Province of Bataan',
                'header_title' => 'Sangguniang Panlalawigan ng Bataan',
            ],
            'types' => [
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER => [
                    UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL => true,
                ],
            ],
        ]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'email' => 'bm-branding@example.com',
            'is_active' => true,
        ]);

        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL,
            'title' => 'Incoming agenda for referral',
            'body' => 'Branding body',
        ]);

        app(EmailNotificationService::class)->sendForNotification(
            $user,
            $notification,
            EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
            force: true,
            vars: [
                'email_subject' => 'Incoming agenda for referral',
                'summary' => 'Branding body',
            ],
        );

        Mail::assertSent(SystemNotificationMail::class, function (SystemNotificationMail $mail) use ($user) {
            $html = $mail->render();

            return $mail->hasTo($user->email)
                && $mail->branding['sign_off'] === 'Respectfully,'
                && $mail->branding['signature'] === 'SPLIS Admin Office'
                && str_contains($html, 'Province of Bataan')
                && str_contains($html, 'Sangguniang Panlalawigan ng Bataan')
                && str_contains($html, 'Respectfully,')
                && str_contains($html, 'SPLIS Admin Office')
                && str_contains($html, 'dashboard-hero-bg.png');
        });
    }

    public function test_disabled_audience_type_does_not_send_email(): void
    {
        Mail::fake();

        app(EmailNotificationSettings::class)->update([
            'enabled' => true,
            'types' => [
                EmailNotificationSettings::AUDIENCE_BOARD_MEMBER => [
                    UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL => false,
                ],
            ],
        ]);

        $user = User::factory()->create([
            'role' => UserRole::BoardMember,
            'email' => 'bm@example.com',
            'is_active' => true,
        ]);

        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL,
            'title' => 'Incoming agenda for referral',
            'body' => 'Test body',
        ]);

        app(EmailNotificationService::class)->sendForNotification(
            $user,
            $notification,
            EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
            force: true,
        );

        Mail::assertNothingSent();
    }

    public function test_board_member_committee_report_emails_staff(): void
    {
        Mail::fake();
        Storage::fake('local');

        $boardMember = BoardMember::query()->create([
            'name' => 'Maria Santos',
            'is_active' => true,
        ]);
        $bmUser = User::factory()->create([
            'role' => UserRole::BoardMember,
            'board_member_id' => $boardMember->id,
            'email' => 'bm@example.com',
            'is_active' => true,
        ]);
        $encoder = User::factory()->create([
            'role' => UserRole::Encoder,
            'email' => 'encoder@example.com',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@example.com',
            'is_active' => true,
        ]);

        $pdf = UploadedFile::fake()->create('committee-report.pdf', 50, 'application/pdf');

        $this->actingAs($bmUser)
            ->post(route('board-member.committee-reports.store'), [
                'title' => 'Ways and Means Report',
                'pdf' => $pdf,
                'agenda_item_ids' => [],
            ])
            ->assertRedirect(route('board-member.committee-reports.index'));

        Mail::assertSent(SystemNotificationMail::class, function (SystemNotificationMail $mail) use ($encoder) {
            return $mail->hasTo($encoder->email)
                && str_contains($mail->notificationBody, 'Maria Santos');
        });
        Mail::assertSent(SystemNotificationMail::class, fn (SystemNotificationMail $mail) => $mail->hasTo($admin->email));
        Mail::assertNotSent(SystemNotificationMail::class, fn (SystemNotificationMail $mail) => $mail->hasTo($bmUser->email));
    }

    public function test_staff_submitted_committee_report_does_not_email_staff(): void
    {
        Mail::fake();
        Storage::fake('local');

        $boardMember = BoardMember::query()->create([
            'name' => 'Maria Santos',
            'is_active' => true,
        ]);
        $encoder = User::factory()->create([
            'role' => UserRole::Encoder,
            'email' => 'encoder@example.com',
            'is_active' => true,
        ]);
        User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@example.com',
            'is_active' => true,
        ]);

        $pdf = UploadedFile::fake()->create('committee-report.pdf', 50, 'application/pdf');

        $this->actingAs($encoder)
            ->post(route('committee-reports.store'), [
                'board_member_id' => $boardMember->id,
                'title' => 'Staff filed report',
                'pdf' => $pdf,
                'agenda_item_ids' => [],
            ])
            ->assertRedirect(route('committee-reports.index'));

        Mail::assertNothingSent();
    }
}
