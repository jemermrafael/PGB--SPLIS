<?php

namespace App\Services;

use App\Models\AppropriationOrdinance;
use App\Models\AppropriationOrdinanceVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppropriationOrdinanceVersionService
{
    /**
     * @var list<string>
     */
    public const VERSIONED_FIELDS = [
        'subject',
        'ordinance_no',
        'series_year',
        'date_received',
        'date_passed',
        'date_approved',
        'pdf_url',
        'pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'subject' => 'Title',
            'ordinance_no' => 'Appro. Ord. No.',
            'series_year' => 'Series year',
            'date_received' => 'Date received',
            'date_passed' => 'Date passed by the SP',
            'date_approved' => 'Date approved by the Governor',
            'pdf_url' => 'File URL',
            'pdf_path' => 'File (local)',
        ];
    }

    public function formatSnapshotDisplayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, ['date_received', 'date_passed', 'date_approved'], true)) {
            return Carbon::parse($value)->format('M j, Y');
        }

        if ($field === 'ordinance_no') {
            return str_pad((string) $value, 2, '0', STR_PAD_LEFT);
        }

        if ($field === 'pdf_path') {
            return basename((string) $value);
        }

        return (string) $value;
    }

    public function preservePdfInCurrentVersion(AppropriationOrdinance $record, ?int $userId = null): void
    {
        if ($record->versions()->doesntExist()) {
            $this->recordInitialVersion($record, $userId);

            return;
        }

        /** @var AppropriationOrdinanceVersion|null $current */
        $current = $record->versions()->where('version_no', $record->current_version_no)->first()
            ?? $record->versions()->orderByDesc('version_no')->first();

        if ($current === null) {
            return;
        }

        $snapshot = $current->snapshot ?? [];
        $changed = false;

        foreach (['pdf_path', 'pdf_url'] as $field) {
            $live = $record->getAttribute($field);

            if (! filled($snapshot[$field] ?? null) && filled($live)) {
                $snapshot[$field] = $live;
                $changed = true;
            }
        }

        if ($changed) {
            $current->forceFill(['snapshot' => $snapshot])->save();
        }
    }

    public function recordInitialVersion(
        AppropriationOrdinance $record,
        ?int $userId = null,
        string $reason = 'encoded',
    ): AppropriationOrdinanceVersion {
        return $this->createVersion($record, $reason, $userId);
    }

    public function resetToImportedVersion(AppropriationOrdinance $record, ?int $userId = null): AppropriationOrdinanceVersion
    {
        return DB::transaction(function () use ($record, $userId): AppropriationOrdinanceVersion {
            $record->versions()->delete();
            $record->forceFill(['current_version_no' => 1])->saveQuietly();

            return AppropriationOrdinanceVersion::create([
                'appropriation_ordinance_id' => $record->id,
                'version_no' => 1,
                'change_reason' => 'imported',
                'snapshot' => $this->snapshotFrom($record),
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     */
    public function recordVersionIfChanged(
        AppropriationOrdinance $record,
        array $originalAttributes,
        ?int $userId = null,
        ?string $forcedReason = null,
    ): ?AppropriationOrdinanceVersion {
        if (! $this->hasVersionableChanges($originalAttributes, $record)) {
            return null;
        }

        return $this->createVersion(
            $record,
            $forcedReason ?? $this->inferChangeReason($originalAttributes, $record),
            $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     */
    public function hasVersionableChanges(array $originalAttributes, AppropriationOrdinance $record): bool
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            $before = $this->normalizeSnapshotValue($field, $originalAttributes[$field] ?? null);
            $after = $this->normalizeSnapshotValue($field, $record->getAttribute($field));

            if ($before !== $after) {
                return true;
            }
        }

        return false;
    }

    public function createVersion(
        AppropriationOrdinance $record,
        string $reason,
        ?int $userId = null,
    ): AppropriationOrdinanceVersion {
        $nextVersion = (int) ($record->versions()->max('version_no') ?? 0) + 1;

        $version = AppropriationOrdinanceVersion::create([
            'appropriation_ordinance_id' => $record->id,
            'version_no' => $nextVersion,
            'change_reason' => $reason,
            'snapshot' => $this->snapshotFrom($record),
            'created_by' => $userId,
        ]);

        $record->forceFill(['current_version_no' => $nextVersion])->saveQuietly();

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFrom(AppropriationOrdinance $record): array
    {
        $snapshot = [];

        foreach (self::VERSIONED_FIELDS as $field) {
            $value = $record->getAttribute($field);

            if ($value instanceof \DateTimeInterface) {
                $snapshot[$field] = $value->format('Y-m-d');
            } else {
                $snapshot[$field] = $value;
            }
        }

        return $snapshot;
    }

    /**
     * @return list<array{field: string, label: string, from: ?string, to: ?string}>
     */
    public function changedFields(?AppropriationOrdinanceVersion $previous, AppropriationOrdinanceVersion $current): array
    {
        if ($previous === null) {
            return [];
        }

        $rows = [];
        $left = $previous->snapshot ?? [];
        $right = $current->snapshot ?? [];

        foreach (self::fieldLabels() as $field => $label) {
            $before = $this->normalizeSnapshotValue($field, $left[$field] ?? null);
            $after = $this->normalizeSnapshotValue($field, $right[$field] ?? null);

            if ($before === $after) {
                continue;
            }

            $rows[] = [
                'field' => $field,
                'label' => $label,
                'from' => $this->formatSnapshotDisplayValue($field, $left[$field] ?? null),
                'to' => $this->formatSnapshotDisplayValue($field, $right[$field] ?? null),
            ];
        }

        return $rows;
    }

    public function deleteVersion(AppropriationOrdinanceVersion $version): void
    {
        $record = $version->appropriationOrdinance;

        if ($record->versions()->count() <= 1) {
            throw new \RuntimeException('Cannot delete the only remaining version.');
        }

        $wasCurrent = $version->version_no === $record->current_version_no;

        DB::transaction(function () use ($version, $record, $wasCurrent): void {
            $version->delete();

            if (! $wasCurrent) {
                return;
            }

            /** @var AppropriationOrdinanceVersion|null $replacement */
            $replacement = $record->versions()->orderByDesc('version_no')->first();

            if ($replacement === null) {
                return;
            }

            $this->applySnapshotToRecord($record, $replacement->snapshot ?? []);
            $record->forceFill(['current_version_no' => $replacement->version_no])->saveQuietly();
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function applySnapshotToRecord(AppropriationOrdinance $record, array $snapshot): void
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            if (! array_key_exists($field, $snapshot)) {
                continue;
            }

            $value = $snapshot[$field];

            if ($value === null || $value === '') {
                $record->setAttribute($field, null);

                continue;
            }

            if (in_array($field, ['ordinance_no', 'series_year'], true)) {
                $record->setAttribute($field, (int) $value);

                continue;
            }

            $record->setAttribute($field, $value);
        }

        $record->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $before
     */
    protected function inferChangeReason(array $before, AppropriationOrdinance $after): string
    {
        $titleChanged = $this->normalizeSnapshotValue('subject', $before['subject'] ?? null)
            !== $this->normalizeSnapshotValue('subject', $after->getAttribute('subject'));
        $pdfChanged = $this->anyFieldChanged($before, $after, ['pdf_path', 'pdf_url']);

        if ($titleChanged && ! $pdfChanged && ! $this->anyFieldChanged($before, $after, array_diff(
            self::VERSIONED_FIELDS,
            ['subject', 'pdf_path', 'pdf_url'],
        ))) {
            return 'title';
        }

        if ($pdfChanged && ! $titleChanged && ! $this->anyFieldChanged($before, $after, array_diff(
            self::VERSIONED_FIELDS,
            ['pdf_path', 'pdf_url'],
        ))) {
            return 'pdf';
        }

        return 'general';
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  list<string>  $fields
     */
    protected function anyFieldChanged(array $before, AppropriationOrdinance $after, array $fields): bool
    {
        foreach ($fields as $field) {
            $previous = $this->normalizeSnapshotValue($field, $before[$field] ?? null);
            $current = $this->normalizeSnapshotValue($field, $after->getAttribute($field));

            if ($previous !== $current) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeSnapshotValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
