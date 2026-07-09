<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Services\ActivityLogNotifier;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Central audit trail for SPLIS actions (user and system).
 *
 * Use three separate layers — do not mix them:
 * - activity_logs (this): auditable events in SPLIS (create, update, link, import batch, …)
 * - sp_rec_added / sp_rec_modified: read-only legacy timestamps copied from sptrack.Files
 * - Eloquent created_at / updated_at: when the SPLIS row itself was created or last saved
 */
class ActivityLogger
{
    public static function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?int $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): ActivityLog {
        $log = ActivityLog::query()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties !== [] ? $properties : null,
            'created_at' => $occurredAt ?? now(),
        ]);

        app(ActivityLogNotifier::class)->notify($log);

        return $log;
    }
}
