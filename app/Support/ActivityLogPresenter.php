<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\AgendaItem;
use App\Models\IncomingDocument;
use App\Models\Ordinance;
use App\Models\ReferenceMaterial;
use App\Models\Resolution;

class ActivityLogPresenter
{
    /** @var array<string, string> */
    private const LABELS = [
        'incoming.created' => 'Incoming created',
        'incoming.updated' => 'Incoming updated',
        'incoming.linked' => 'Incoming linked',
        'incoming.imported_from_sptrack' => 'SPTrack import',
        'resolution.created' => 'Resolution created',
        'resolution.updated' => 'Resolution updated',
        'resolution.deleted' => 'Resolution deleted',
        'resolution.published_from_incoming' => 'Published from incoming',
        'reference_material.created' => 'Reference material created',
        'reference_material.updated' => 'Reference material updated',
        'reference_material.archived' => 'Reference material archived',
        'reference_material.restored' => 'Reference material restored',
        'reference_material.deleted' => 'Reference material deleted',
        'data_sync.resolutions_csv' => 'Resolutions CSV sync',
        'data_sync.sptrack_incoming' => 'SPTrack incoming sync',
        'data_sync.agenda_csv' => 'Agenda CSV sync',
        'data_sync.sptrack_resolutions' => 'SPTrack resolutions sync',
        'backup.created' => 'Database backup created',
        'backup.settings_updated' => 'Backup settings updated',
        'backup.downloaded' => 'Database backup downloaded',
        'backup.restored' => 'Database restored',
        'agenda.added_to_ob' => 'Added to Order of Business',
        'agenda.removed_from_ob' => 'Removed from Order of Business',
        'agenda.ob_relocated' => 'Moved in Order of Business',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? str_replace('.', ' ', ucfirst($action));
    }

    public static function body(ActivityLog $log): string
    {
        $actor = $log->user?->name ?? 'System';
        $details = [];

        if (! empty($log->properties['resolution_no'])) {
            $details[] = 'Resolution '.$log->properties['resolution_no'];
        }

        if (! empty($log->properties['source'])) {
            $details[] = 'Source: '.$log->properties['source'];
        }

        if (! empty($log->properties['section_label'])) {
            $details[] = $log->properties['section_label'];
        }

        if (! empty($log->properties['session_title'])) {
            $details[] = $log->properties['session_title'];
        }

        if ($details === []) {
            return $actor;
        }

        return $actor.' · '.implode(' · ', $details);
    }

    public static function link(ActivityLog $log): ?string
    {
        if ($log->subject_type === null || $log->subject_id === null) {
            return null;
        }

        return match ($log->subject_type) {
            Resolution::class => route('resolutions.show', $log->subject_id),
            IncomingDocument::class => route('incoming.show', $log->subject_id),
            AgendaItem::class => route('agenda.show', $log->subject_id),
            Ordinance::class => route('ordinances.show', $log->subject_id),
            ReferenceMaterial::class => route('references.show', $log->subject_id),
            default => null,
        };
    }

    public static function linkLabel(ActivityLog $log): string
    {
        return match ($log->subject_type) {
            AgendaItem::class => 'View agenda',
            Resolution::class => 'View resolution',
            IncomingDocument::class => 'View incoming',
            Ordinance::class => 'View ordinance',
            ReferenceMaterial::class => 'View reference',
            default => 'View details',
        };
    }
}
