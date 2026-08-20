<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ActivityLogPresenter;
use App\Services\UserNotificationPreferenceService;
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

    public const OB_FINALIZED_TITLE = 'Order of Business published';

    public const OB_ADDED_DIGEST_TITLE = 'Agendas added to Order of Business';

    public const SESSION_EDIT_ACTIONS = [
        'legislative_session.status_changed',
        'legislative_session.drive_link_updated',
        'legislative_session.pdf_uploaded',
        'legislative_session.final_minutes_tags_updated',
        'legislative_session.final_journal_tags_updated',
        'committee_report_summary.updated',
    ];

    public const SESSION_EDIT_DIGEST_TITLE = 'Session updated';

    /** Coalesce subsequent post-final adds into one admin notification within this window. */
    public const OB_DIGEST_COALESCE_MINUTES = 30;

    /** Merge session-edit pings for the same session into one inbox item. */
    public const SESSION_EDIT_DIGEST_COALESCE_MINUTES = 30;

    public function __construct(
        protected EmailNotificationService $emails,
        protected UserNotificationPreferenceService $preferences,
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

        if (in_array($log->action, self::SESSION_EDIT_ACTIONS, true)) {
            $this->digestSessionEdit($log);

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
     * Keep per-edit History rows, but fold inbox alerts into one "Session updated" item.
     */
    protected function digestSessionEdit(ActivityLog $log): void
    {
        $session = $this->sessionFromLog($log);

        if ($session === null) {
            return;
        }

        $title = self::SESSION_EDIT_DIGEST_TITLE;
        $body = $this->sessionEditDigestBody($session);
        $link = route('ob.sessions.show', $session, absolute: false);

        foreach ($this->sessionEditRecipients() as $user) {
            $this->upsertSessionEditDigest($user, $session, $title, $body, $link);
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function sessionEditRecipients(): Collection
    {
        $admins = $this->activeAdmins();

        $boardMembers = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::BoardMember)
            ->get()
            ->filter(fn (User $user) => $this->preferences->allowsInApp($user, UserNotification::TYPE_ACTIVITY_LOG));

        return $admins->concat($boardMembers)->unique('id')->values();
    }

    protected function upsertSessionEditDigest(
        User $user,
        LegislativeSession $session,
        string $title,
        string $body,
        string $link,
    ): void {
        $existing = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('type', UserNotification::TYPE_ACTIVITY_LOG)
            ->where('legislative_session_id', $session->id)
            ->where('title', $title)
            ->where('created_at', '>=', now()->subMinutes(self::SESSION_EDIT_DIGEST_COALESCE_MINUTES))
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            $existing->forceFill([
                'body' => $body,
                'link' => $link,
                'read_at' => null,
                'created_at' => now(),
            ])->save();

            return;
        }

        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_ACTIVITY_LOG,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'legislative_session_id' => $session->id,
            'activity_log_id' => null,
        ]);

        $audience = $user->isBoardMember()
            ? EmailNotificationSettings::AUDIENCE_BOARD_MEMBER
            : EmailNotificationSettings::AUDIENCE_STAFF;

        $this->emails->sendForNotification(
            $user,
            $notification,
            $audience,
            vars: [
                'title' => $title,
                'body' => $body,
            ],
            emailType: UserNotification::TYPE_ACTIVITY_LOG,
        );
    }

    protected function sessionFromLog(ActivityLog $log): ?LegislativeSession
    {
        if ($log->subject_type !== LegislativeSession::class || $log->subject_id === null) {
            return null;
        }

        return LegislativeSession::withTrashed()->find($log->subject_id);
    }

    protected function sessionEditDigestBody(LegislativeSession $session): string
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->whereIn('action', self::SESSION_EDIT_ACTIONS)
            ->where('subject_type', LegislativeSession::class)
            ->where('subject_id', $session->id)
            ->where('created_at', '>=', now()->subMinutes(self::SESSION_EDIT_DIGEST_COALESCE_MINUTES))
            ->orderBy('id')
            ->get();

        $labels = $logs
            ->map(fn (ActivityLog $item) => $this->sessionEditChangeLabel($item))
            ->filter()
            ->unique()
            ->values();

        $actor = $logs->last()?->user?->name ?? 'System';
        $details = $labels->isEmpty() ? ['Session details updated'] : $labels->all();

        return $actor.' · '.$session->displayTitle().' · '.implode(', ', $details);
    }

    protected function sessionEditChangeLabel(ActivityLog $log): string
    {
        $properties = $log->properties ?? [];

        return match ($log->action) {
            'legislative_session.status_changed' => trim(sprintf(
                'Status: %s → %s',
                ucfirst((string) ($properties['from_status'] ?? '')),
                ucfirst((string) ($properties['to_status'] ?? '')),
            )),
            'legislative_session.drive_link_updated' => trim((string) ($properties['slot_label'] ?? 'Drive link')).' updated',
            'legislative_session.pdf_uploaded' => trim((string) ($properties['slot_label'] ?? 'PDF')).' uploaded',
            'legislative_session.final_minutes_tags_updated' => 'Final Minutes agendas tagged',
            'legislative_session.final_journal_tags_updated' => 'Final Journal agendas tagged',
            'committee_report_summary.updated' => 'Committee Report Summary saved',
            default => ActivityLogPresenter::label($log),
        };
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
