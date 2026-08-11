<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ActivityLogPresenter;
use Illuminate\Support\Collection;

class ActivityLogNotifier
{
    /**
     * Noisy operational events that should not notify admin/encoder inboxes.
     *
     * @var list<string>
     */
    public const HIDDEN_ACTIONS = [
        'ordinance.pdf_mirrored',
        'appropriation_ordinance.pdf_mirrored',
        'backup.created',
        'data_sync.drive_mirror_process',
        'data_sync.agenda_csv',
        'data_sync.link_pdfs',
        'data_sync.resolutions_csv',
    ];

    /**
     * OB placement activity — history is kept; admin inbox uses digests instead of per-agenda alerts.
     *
     * @var list<string>
     */
    public const OB_AGENDA_ACTIONS = [
        'agenda.added_to_ob',
        'agenda.removed_from_ob',
        'agenda.ob_relocated',
    ];

    public const OB_FINALIZED_TITLE = 'Order of Business finalized';

    public const OB_ADDED_DIGEST_TITLE = 'Agendas added to Order of Business';

    /** Coalesce subsequent post-final adds into one admin notification within this window. */
    public const OB_DIGEST_COALESCE_MINUTES = 30;

    public function __construct(
        protected EmailNotificationService $emails,
    ) {}

    public function notify(ActivityLog $log): void
    {
        if (in_array($log->action, self::HIDDEN_ACTIONS, true)) {
            return;
        }

        // OB placement: keep History, but never ping admins per agenda / move / remove.
        // Finalize and subsequent-add digests are sent via dedicated methods.
        if ($this->isObAgendaAction($log->action)) {
            return;
        }

        $log->loadMissing('user');

        $admins = $this->activeAdmins();

        if ($admins->isEmpty()) {
            return;
        }

        $title = ActivityLogPresenter::label($log);
        $body = ActivityLogPresenter::body($log);
        $link = ActivityLogPresenter::link($log);

        foreach ($admins as $admin) {
            $notification = UserNotification::query()->firstOrCreate(
                [
                    'user_id' => $admin->id,
                    'activity_log_id' => $log->id,
                ],
                [
                    'type' => UserNotification::TYPE_ACTIVITY_LOG,
                    'title' => $title,
                    'body' => $body,
                    'link' => $link,
                ],
            );

            $this->emails->sendForNotification(
                $admin,
                $notification,
                EmailNotificationSettings::AUDIENCE_STAFF,
                vars: [
                    'title' => $title,
                    'body' => $body,
                ],
                emailType: UserNotification::TYPE_ACTIVITY_LOG,
            );
        }
    }

    /**
     * One admin digest when an OB becomes final (scheduled session): N agendas placed.
     */
    public function notifyPendingObAgendaLogsForSession(LegislativeSession $session): void
    {
        $session->loadMissing('obDocument');

        if (! $session->isNotifiableForObAgendaAdds()) {
            return;
        }

        $count = ActivityLog::query()
            ->where('action', 'agenda.added_to_ob')
            ->where('properties->session_id', $session->id)
            ->count();

        if ($count < 1) {
            return;
        }

        $title = self::OB_FINALIZED_TITLE;
        $body = sprintf(
            '%d %s placed on %s.',
            $count,
            $count === 1 ? 'agenda' : 'agendas',
            $session->displayTitle(),
        );
        $link = route('ob.sessions.show', $session, absolute: false);

        $this->sendSessionDigest($session, $title, $body, $link, coalesce: false);
    }

    /**
     * After OB is already final, coalesce subsequent "added to OB" events into one admin alert.
     */
    public function digestSubsequentObAdds(LegislativeSession $session, int $addedCount): void
    {
        $session->loadMissing('obDocument');

        if ($addedCount < 1 || ! $session->isNotifiableForObAgendaAdds()) {
            return;
        }

        $title = self::OB_ADDED_DIGEST_TITLE;
        $link = route('ob.sessions.show', $session, absolute: false);
        $sessionTitle = $session->displayTitle();

        $admins = $this->activeAdmins();

        if ($admins->isEmpty()) {
            return;
        }

        foreach ($admins as $admin) {
            $existing = UserNotification::query()
                ->where('user_id', $admin->id)
                ->where('type', UserNotification::TYPE_ACTIVITY_LOG)
                ->where('legislative_session_id', $session->id)
                ->where('title', $title)
                ->where('created_at', '>=', now()->subMinutes(self::OB_DIGEST_COALESCE_MINUTES))
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                $total = $this->parseAddedDigestCount((string) $existing->body) + $addedCount;
                $existing->forceFill([
                    'body' => $this->addedDigestBody($total, $sessionTitle),
                    'link' => $link,
                    'read_at' => null,
                ])->save();

                continue;
            }

            $notification = UserNotification::query()->create([
                'user_id' => $admin->id,
                'type' => UserNotification::TYPE_ACTIVITY_LOG,
                'title' => $title,
                'body' => $this->addedDigestBody($addedCount, $sessionTitle),
                'link' => $link,
                'legislative_session_id' => $session->id,
                'activity_log_id' => null,
            ]);

            $this->emails->sendForNotification(
                $admin,
                $notification,
                EmailNotificationSettings::AUDIENCE_STAFF,
                vars: [
                    'title' => $title,
                    'body' => $notification->body,
                ],
                emailType: UserNotification::TYPE_ACTIVITY_LOG,
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function activeAdmins(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Admin, UserRole::Superadmin])
            ->get();
    }

    protected function sendSessionDigest(
        LegislativeSession $session,
        string $title,
        string $body,
        string $link,
        bool $coalesce,
    ): void {
        $admins = $this->activeAdmins();

        if ($admins->isEmpty()) {
            return;
        }

        foreach ($admins as $admin) {
            if ($coalesce) {
                $existing = UserNotification::query()
                    ->where('user_id', $admin->id)
                    ->where('type', UserNotification::TYPE_ACTIVITY_LOG)
                    ->where('legislative_session_id', $session->id)
                    ->where('title', $title)
                    ->where('created_at', '>=', now()->subMinutes(self::OB_DIGEST_COALESCE_MINUTES))
                    ->orderByDesc('id')
                    ->first();

                if ($existing !== null) {
                    $existing->forceFill([
                        'body' => $body,
                        'link' => $link,
                        'read_at' => null,
                    ])->save();

                    continue;
                }
            } else {
                $already = UserNotification::query()
                    ->where('user_id', $admin->id)
                    ->where('type', UserNotification::TYPE_ACTIVITY_LOG)
                    ->where('legislative_session_id', $session->id)
                    ->where('title', $title)
                    ->exists();

                if ($already) {
                    continue;
                }
            }

            $notification = UserNotification::query()->create([
                'user_id' => $admin->id,
                'type' => UserNotification::TYPE_ACTIVITY_LOG,
                'title' => $title,
                'body' => $body,
                'link' => $link,
                'legislative_session_id' => $session->id,
                'activity_log_id' => null,
            ]);

            $this->emails->sendForNotification(
                $admin,
                $notification,
                EmailNotificationSettings::AUDIENCE_STAFF,
                vars: [
                    'title' => $title,
                    'body' => $body,
                ],
                emailType: UserNotification::TYPE_ACTIVITY_LOG,
            );
        }
    }

    protected function addedDigestBody(int $count, string $sessionTitle): string
    {
        return sprintf(
            '%d %s added to %s.',
            $count,
            $count === 1 ? 'agenda' : 'agendas',
            $sessionTitle,
        );
    }

    protected function parseAddedDigestCount(string $body): int
    {
        if (preg_match('/^(\d+)\s+agendas?\s+added\s+to\b/i', $body, $matches) === 1) {
            return max(0, (int) $matches[1]);
        }

        return 0;
    }

    protected function isObAgendaAction(string $action): bool
    {
        return in_array($action, self::OB_AGENDA_ACTIONS, true);
    }
}
