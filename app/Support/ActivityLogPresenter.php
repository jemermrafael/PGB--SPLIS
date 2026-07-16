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
        'resolution.trashed' => 'Resolution moved to trash',
        'resolution.restored' => 'Resolution restored',
        'resolution.deleted' => 'Resolution permanently deleted',
        'resolution.published_from_incoming' => 'Published from incoming',
        'agenda.created' => 'Agenda created',
        'agenda.published' => 'Agenda published',
        'ordinance.created' => 'Ordinance created',
        'reference_material.created' => 'Reference Material created',
        'reference_material.updated' => 'Reference Material updated',
        'reference_material.archived' => 'Reference Material archived',
        'reference_material.restored' => 'Reference Material restored',
        'reference_material.deleted' => 'Reference Material deleted',
        'data_sync.resolutions_csv' => 'Resolutions CSV sync',
        'data_sync.sptrack_incoming' => 'SPTrack incoming sync',
        'data_sync.agenda_csv' => 'Agenda CSV sync',
        'data_sync.sptrack_resolutions' => 'SPTrack Resolutions sync',
        'backup.created' => 'Database backup created',
        'backup.settings_updated' => 'Backup settings updated',
        'backup.downloaded' => 'Database backup downloaded',
        'backup.restored' => 'Database restored',
        'agenda.added_to_ob' => 'Added to Order of Business',
        'agenda.removed_from_ob' => 'Removed from Order of Business',
        'agenda.ob_relocated' => 'Moved in Order of Business',
    ];

    public static function label(string|ActivityLog $actionOrLog): string
    {
        $action = $actionOrLog instanceof ActivityLog ? $actionOrLog->action : $actionOrLog;
        $properties = $actionOrLog instanceof ActivityLog ? ($actionOrLog->properties ?? []) : [];

        if (! empty($properties['resolution_no']) && in_array($action, ['resolution.trashed', 'resolution.restored', 'resolution.deleted'], true)) {
            $reference = self::resolutionReference($properties);

            return match ($action) {
                'resolution.trashed' => "Resolution {$reference} moved to trash",
                'resolution.restored' => "Resolution {$reference} restored",
                'resolution.deleted' => "Resolution {$reference} permanently deleted",
                default => self::LABELS[$action] ?? str_replace('.', ' ', ucfirst($action)),
            };
        }

        if ($action === 'resolution.deleted' && $actionOrLog instanceof ActivityLog && $actionOrLog->subject_id) {
            $resolution = Resolution::withTrashed()->find($actionOrLog->subject_id);

            if ($resolution) {
                $reference = self::resolutionReference([
                    'resolution_no' => $resolution->resolution_no,
                    'series' => $resolution->series,
                ]);

                if ($resolution->trashed()) {
                    return "Resolution {$reference} moved to trash";
                }
            }
        }

        return self::LABELS[$action] ?? str_replace('.', ' ', ucfirst($action));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected static function resolutionReference(array $properties): string
    {
        $no = (string) $properties['resolution_no'];
        $series = $properties['series'] ?? null;

        return $series ? "{$series}-{$no}" : $no;
    }

    public static function body(ActivityLog $log): string
    {
        $actor = $log->user?->name ?? 'System';
        $details = [];

        if (! empty($log->properties['resolution_no'])) {
            $details[] = self::resolutionReference($log->properties);
        }

        if (! empty($log->properties['ordinance_no'])) {
            $series = $log->properties['series_year'] ?? null;
            $details[] = 'Ordinance '.($series ? $series.'-' : '').$log->properties['ordinance_no'];
        }

        if (! empty($log->properties['tracking_no'])) {
            $details[] = 'Tracking '.$log->properties['tracking_no'];
        }

        if (! empty($log->properties['target'])) {
            $details[] = $log->properties['target'];
        }

        if (! empty($log->properties['output_no'])) {
            $details[] = $log->properties['output_no'];
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
            Resolution::class => route('resolutions.show', $log->subject_id, absolute: false),
            IncomingDocument::class => route('incoming.show', $log->subject_id, absolute: false),
            AgendaItem::class => route('agenda.show', $log->subject_id, absolute: false),
            Ordinance::class => route('ordinances.show', $log->subject_id, absolute: false),
            ReferenceMaterial::class => route('references.show', $log->subject_id, absolute: false),
            default => null,
        };
    }

    public static function linkLabel(ActivityLog $log): string
    {
        if ($log->action === 'resolution.trashed') {
            return 'View in trash';
        }

        if ($log->action === 'resolution.deleted' && $log->subject_type === Resolution::class && $log->subject_id) {
            $resolution = Resolution::withTrashed()->find($log->subject_id);

            if ($resolution?->trashed()) {
                return 'View in trash';
            }
        }

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
