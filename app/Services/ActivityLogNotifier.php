<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ActivityLog;
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

    public function notify(ActivityLog $log): void
    {
        if (in_array($log->action, self::HIDDEN_ACTIONS, true)) {
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
            UserNotification::query()->firstOrCreate(
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
        }
    }
}
