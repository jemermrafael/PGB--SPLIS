<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\SystemNotificationMail;
use App\Models\BoardMemberCommitteeReport;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\EmailHtml;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailNotificationService
{
    public function __construct(
        protected EmailNotificationSettings $settings,
        protected UserNotificationPreferenceService $preferences,
    ) {}

    /**
     * @param  array<string, string|null>  $vars
     */
    public function sendForNotification(
        User $user,
        UserNotification $notification,
        string $audience,
        bool $force = false,
        array $vars = [],
        ?string $emailType = null,
    ): void {
        if (! $force && ! $notification->wasRecentlyCreated) {
            return;
        }

        $type = $emailType ?: (string) $notification->type;

        $this->sendTemplated(
            $user,
            $audience,
            $type,
            array_merge([
                'title' => (string) $notification->title,
                'body' => (string) $notification->body,
            ], $vars),
            $notification->link ? url($notification->link) : null,
        );
    }

    /**
     * @param  array<string, string|null>  $vars
     */
    public function sendTemplated(
        User $user,
        string $audience,
        string $type,
        array $vars = [],
        ?string $actionUrl = null,
    ): void {
        if (! $this->settings->typeEnabled($audience, $type)) {
            return;
        }

        if ($user->isBoardMember() && ! $this->preferences->allowsEmail($user, $type)) {
            return;
        }

        $email = trim((string) $user->email);
        if ($email === '' || ! $user->is_active) {
            return;
        }

        $template = $this->settings->template($audience, $type);
        $vars = array_merge([
            'app_name' => (string) config('app.name'),
            'title' => '',
            'body' => '',
        ], $vars);

        $subject = EmailHtml::plainSubject($this->render($template['subject'], $vars));
        $body = EmailHtml::toEmailHtml($this->render($template['body'], $vars));
        $actionLabel = EmailHtml::plainSubject($this->render($template['action_label'], $vars));

        $this->deliver($email, $subject, $body, $actionUrl, $actionLabel, [
            'user_id' => $user->id,
            'audience' => $audience,
            'type' => $type,
        ]);
    }

    public function notifyStaffOfCommitteeReport(BoardMemberCommitteeReport $report, User $submitter): void
    {
        if (! $submitter->isBoardMember()) {
            return;
        }

        if (! $this->settings->typeEnabled(
            EmailNotificationSettings::AUDIENCE_STAFF,
            EmailNotificationSettings::TYPE_COMMITTEE_REPORT_SUBMITTED,
        )) {
            return;
        }

        $report->loadMissing('boardMember');

        $memberName = $report->boardMember?->displayName()
            ?: ($submitter->name ?: 'A board member');

        $reportTitle = trim((string) ($report->title ?? ''));
        $url = route('committee-reports.index');

        foreach ($this->staffRecipients(excludeUserId: $submitter->id) as $recipient) {
            $this->sendTemplated(
                $recipient,
                EmailNotificationSettings::AUDIENCE_STAFF,
                EmailNotificationSettings::TYPE_COMMITTEE_REPORT_SUBMITTED,
                [
                    'member_name' => $memberName,
                    'report_title' => $reportTitle,
                    'report_title_suffix' => $reportTitle !== '' ? ': '.$reportTitle : '',
                    'title' => 'Committee report submitted',
                    'body' => $memberName.' submitted a committee report'.($reportTitle !== '' ? ': '.$reportTitle : '').'.',
                ],
                $url,
            );
        }
    }

    /**
     * @param  array<string, string|null>  $vars
     */
    public function notifyStaff(string $type, array $vars = [], ?string $actionUrl = null, ?int $excludeUserId = null): void
    {
        if (! $this->settings->typeEnabled(EmailNotificationSettings::AUDIENCE_STAFF, $type)) {
            return;
        }

        foreach ($this->staffRecipients($excludeUserId) as $recipient) {
            $this->sendTemplated(
                $recipient,
                EmailNotificationSettings::AUDIENCE_STAFF,
                $type,
                $vars,
                $actionUrl,
            );
        }
    }

    public function resolvePublishedEmailType(string $audience, string $target): string
    {
        $specific = match (true) {
            strcasecmp($target, 'Resolution') === 0 => EmailNotificationSettings::TYPE_RESOLUTION_PUBLISHED,
            strcasecmp($target, 'Appropriation Ordinance') === 0 => EmailNotificationSettings::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED,
            strcasecmp($target, 'Ordinance') === 0 => EmailNotificationSettings::TYPE_ORDINANCE_PUBLISHED,
            default => null,
        };

        if ($audience === EmailNotificationSettings::AUDIENCE_BOARD_MEMBER) {
            if ($specific !== null && $this->settings->typeEnabled($audience, $specific)) {
                return $specific;
            }

            return UserNotification::TYPE_AGENDA_PUBLISHED;
        }

        if ($specific === EmailNotificationSettings::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED) {
            if ($this->settings->typeEnabled($audience, $specific)) {
                return $specific;
            }

            return EmailNotificationSettings::TYPE_ORDINANCE_PUBLISHED;
        }

        return $specific ?? EmailNotificationSettings::TYPE_ORDINANCE_PUBLISHED;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function staffRecipients(?int $excludeUserId = null)
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [
                UserRole::Encoder,
                UserRole::EncoderDelete,
                UserRole::Admin,
                UserRole::Superadmin,
            ])
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->get();
    }

    public function sendTest(string $toEmail, string $title, string $body, ?string $actionUrl = null): void
    {
        $this->deliver(
            $toEmail,
            EmailHtml::plainSubject($title),
            EmailHtml::toEmailHtml($body),
            $actionUrl,
            'Open settings',
            [
                'type' => 'test',
            ],
            throwOnFailure: true,
        );
    }

    /**
     * @param  array<string, string|null>  $vars
     */
    public function render(string $template, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) ($value ?? '');
        }

        return strtr($template, $replacements);
    }

    public function applyMailConfig(): void
    {
        $smtp = $this->settings->get()['smtp'];
        $mailer = $smtp['mailer'] !== '' ? $smtp['mailer'] : 'log';

        Config::set('mail.default', $mailer);
        Config::set('mail.from.address', $smtp['from_address'] !== '' ? $smtp['from_address'] : config('mail.from.address'));
        Config::set('mail.from.name', $smtp['from_name'] !== '' ? $smtp['from_name'] : config('mail.from.name'));

        if ($mailer === 'smtp') {
            $encryption = strtolower(trim((string) ($smtp['encryption'] ?? '')));
            // Laravel/Symfony: port 465 => smtps, otherwise smtp (STARTTLS). Do not pass "tls"/"ssl" as scheme.
            $scheme = match ($encryption) {
                'ssl' => 'smtps',
                'tls' => 'smtp',
                default => null,
            };

            $password = (string) ($smtp['password'] ?? '');
            // Gmail app passwords are often copied with spaces.
            $password = preg_replace('/\s+/', '', $password) ?? $password;

            Config::set('mail.mailers.smtp.host', $smtp['host']);
            Config::set('mail.mailers.smtp.port', $smtp['port']);
            Config::set('mail.mailers.smtp.username', $smtp['username'] !== '' ? $smtp['username'] : null);
            Config::set('mail.mailers.smtp.password', $password !== '' ? $password : null);
            Config::set('mail.mailers.smtp.scheme', $scheme);
        }

        if ($mailer === 'smtp' && app()->bound('mail.manager')) {
            app('mail.manager')->purge('smtp');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function deliver(
        string $toEmail,
        string $title,
        string $body,
        ?string $actionUrl,
        ?string $actionLabel,
        array $context = [],
        bool $throwOnFailure = false,
    ): void {
        $this->applyMailConfig();

        try {
            Mail::to($toEmail)->send(new SystemNotificationMail(
                notificationTitle: $title,
                notificationBody: $body,
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
            ));
        } catch (Throwable $e) {
            Log::warning('Failed to send email notification.', array_merge($context, [
                'email' => $toEmail,
                'error' => $e->getMessage(),
            ]));

            if ($throwOnFailure) {
                throw $e;
            }
        }
    }
}
