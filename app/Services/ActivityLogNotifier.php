<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ActivityLogPresenter;

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
     * OB placement activity — only notify when the session is scheduled and OB is final.
     *
     * @var list<string>
     */
    public const OB_AGENDA_ACTIONS = [
        'agenda.added_to_ob',
        'agenda.removed_from_ob',
        'agenda.ob_relocated',
    ];

    public function __construct(
        protected EmailNotificationService $emails,
    ) {}

    public function notify(ActivityLog $log): void
    {
        if (in_array($log->action, self::HIDDEN_ACTIONS, true)) {
            return;
        }

        if ($this->isObAgendaAction($log->action) && ! $this->obAgendaActionIsNotifiable($log)) {
            return;
        }

        $log->loadMissing('user');

        $admins = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Admin, UserRole::Superadmin])
            ->get();

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
     * Flush deferred OB activity notifications once a session qualifies (scheduled + final OB).
     */
    public function notifyPendingObAgendaLogsForSession(LegislativeSession $session): void
    {
        $session->loadMissing('obDocument');

        if (! $session->isNotifiableForObAgendaAdds()) {
            return;
        }

        ActivityLog::query()
            ->whereIn('action', self::OB_AGENDA_ACTIONS)
            ->where('properties->session_id', $session->id)
            ->orderBy('id')
            ->each(fn (ActivityLog $log) => $this->notify($log));
    }

    protected function isObAgendaAction(string $action): bool
    {
        return in_array($action, self::OB_AGENDA_ACTIONS, true);
    }

    protected function obAgendaActionIsNotifiable(ActivityLog $log): bool
    {
        $sessionId = (int) ($log->properties['session_id'] ?? 0);

        if ($sessionId <= 0) {
            return false;
        }

        $session = LegislativeSession::query()
            ->with('obDocument')
            ->find($sessionId);

        return $session?->isNotifiableForObAgendaAdds() ?? false;
    }
}
