<?php

namespace App\Services;

use App\Models\Ordinance;
use App\Models\OrdinanceVersion;
use App\Support\OrdinancePdfType;
use Illuminate\Support\Facades\DB;

class OrdinanceVersionService
{
    /**
     * @var list<string>
     */
    public const VERSIONED_FIELDS = [
        'title',
        'pdf_url',
        'pdf_path',
        'mov_bulletin_url',
        'mov_bulletin_pdf_path',
        'mov_certification_url',
        'mov_certification_pdf_path',
        'mov_newspaper_url',
        'mov_newspaper_pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'title' => 'Title',
            'pdf_url' => 'Ordinance PDF URL',
            'pdf_path' => 'Ordinance PDF (local)',
            'mov_bulletin_url' => 'Bulletin PDF URL',
            'mov_bulletin_pdf_path' => 'Bulletin PDF (local)',
            'mov_certification_url' => 'Certification PDF URL',
            'mov_certification_pdf_path' => 'Certification PDF (local)',
            'mov_newspaper_url' => 'Newspaper PDF URL',
            'mov_newspaper_pdf_path' => 'Newspaper PDF (local)',
        ];
    }

    public function formatSnapshotDisplayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_ends_with($field, '_path')) {
            return basename((string) $value);
        }

        return (string) $value;
    }

    /**
     * Ensure the current version snapshot still points at live PDFs before a replacement upload.
     */
    public function preservePdfsInCurrentVersion(Ordinance $ordinance, ?int $userId = null): void
    {
        if ($ordinance->versions()->doesntExist()) {
            $this->recordInitialVersion($ordinance, $userId);

            return;
        }

        /** @var OrdinanceVersion|null $current */
        $current = $ordinance->versions()->where('version_no', $ordinance->current_version_no)->first()
            ?? $ordinance->versions()->orderByDesc('version_no')->first();

        if ($current === null) {
            return;
        }

        $snapshot = $current->snapshot ?? [];
        $changed = false;

        foreach (self::VERSIONED_FIELDS as $field) {
            if ($field === 'title') {
                continue;
            }

            $live = $ordinance->getAttribute($field);

            if (! filled($snapshot[$field] ?? null) && filled($live)) {
                $snapshot[$field] = $live;
                $changed = true;
            }
        }

        if ($changed) {
            $current->forceFill(['snapshot' => $snapshot])->save();
        }
    }

    public function recordInitialVersion(Ordinance $ordinance, ?int $userId = null): OrdinanceVersion
    {
        return $this->createVersion($ordinance, 'encoded', $userId);
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     */
    public function recordVersionIfChanged(Ordinance $ordinance, array $originalAttributes, ?int $userId = null): ?OrdinanceVersion
    {
        if (! $this->hasVersionableChanges($originalAttributes, $ordinance)) {
            return null;
        }

        return $this->createVersion(
            $ordinance,
            $this->inferChangeReason($originalAttributes, $ordinance),
            $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     */
    public function hasVersionableChanges(array $originalAttributes, Ordinance $ordinance): bool
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            $before = $this->normalizeSnapshotValue($originalAttributes[$field] ?? null);
            $after = $this->normalizeSnapshotValue($ordinance->getAttribute($field));

            if ($before !== $after) {
                return true;
            }
        }

        return false;
    }

    public function createVersion(Ordinance $ordinance, string $reason, ?int $userId = null): OrdinanceVersion
    {
        $nextVersion = (int) ($ordinance->versions()->max('version_no') ?? 0) + 1;

        $version = OrdinanceVersion::create([
            'ordinance_id' => $ordinance->id,
            'version_no' => $nextVersion,
            'change_reason' => $reason,
            'snapshot' => $this->snapshotFrom($ordinance),
            'created_by' => $userId,
        ]);

        $ordinance->forceFill(['current_version_no' => $nextVersion])->saveQuietly();

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFrom(Ordinance $ordinance): array
    {
        $snapshot = [];

        foreach (self::VERSIONED_FIELDS as $field) {
            $snapshot[$field] = $ordinance->getAttribute($field);
        }

        return $snapshot;
    }

    public function deleteVersion(OrdinanceVersion $version): void
    {
        $ordinance = $version->ordinance;

        if ($ordinance->versions()->count() <= 1) {
            throw new \RuntimeException('Cannot delete the only remaining version.');
        }

        $wasCurrent = $version->version_no === $ordinance->current_version_no;

        DB::transaction(function () use ($version, $ordinance, $wasCurrent): void {
            $version->delete();

            if (! $wasCurrent) {
                return;
            }

            /** @var OrdinanceVersion|null $replacement */
            $replacement = $ordinance->versions()->orderByDesc('version_no')->first();

            if ($replacement === null) {
                return;
            }

            $this->applySnapshotToOrdinance($ordinance, $replacement->snapshot ?? []);
            $ordinance->forceFill(['current_version_no' => $replacement->version_no])->saveQuietly();
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function applySnapshotToOrdinance(Ordinance $ordinance, array $snapshot): void
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            if (! array_key_exists($field, $snapshot)) {
                continue;
            }

            $value = $snapshot[$field];
            $ordinance->setAttribute($field, ($value === null || $value === '') ? null : $value);
        }

        $ordinance->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $before
     */
    protected function inferChangeReason(array $before, Ordinance $after): string
    {
        $titleChanged = $this->normalizeSnapshotValue($before['title'] ?? null)
            !== $this->normalizeSnapshotValue($after->getAttribute('title'));

        $pdfChanged = false;
        foreach (self::VERSIONED_FIELDS as $field) {
            if ($field === 'title') {
                continue;
            }

            if ($this->normalizeSnapshotValue($before[$field] ?? null)
                !== $this->normalizeSnapshotValue($after->getAttribute($field))) {
                $pdfChanged = true;
                break;
            }
        }

        if ($titleChanged && $pdfChanged) {
            return 'general';
        }

        if ($titleChanged) {
            return 'title';
        }

        if ($pdfChanged) {
            return 'pdf';
        }

        return 'general';
    }

    protected function normalizeSnapshotValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return list<string>
     */
    public function pdfUploadFields(): array
    {
        return array_map(
            fn (string $type): string => OrdinancePdfType::config($type)['upload'],
            OrdinancePdfType::all(),
        );
    }
}
