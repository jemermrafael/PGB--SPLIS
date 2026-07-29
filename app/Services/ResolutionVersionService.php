<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Category2;
use App\Models\Category3;
use App\Models\Category4;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Models\ResolutionVersion;
use Illuminate\Support\Facades\DB;

class ResolutionVersionService
{
    /**
     * @var list<string>
     */
    public const VERSIONED_FIELDS = [
        'resolution_no',
        'resolution_title',
        'series',
        'status',
        'date_approved',
        'sponsored_by',
        'department_id',
        'category_id',
        'category2_id',
        'category3_id',
        'category4_id',
        'keyword',
        'committee',
        'app_ord_no',
        'amount',
        'municipality_id',
        'province',
        'pdf_path',
        'sp_pdf_url',
        'document_type',
    ];

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'resolution_no' => 'Resolution No.',
            'resolution_title' => 'Title',
            'series' => 'Series',
            'status' => 'Status',
            'date_approved' => 'Date Approved',
            'sponsored_by' => 'Sponsored By',
            'department_id' => 'Department',
            'category_id' => 'Category',
            'category2_id' => 'Sub-Category 1',
            'category3_id' => 'Sub-Category 2',
            'category4_id' => 'Sub-Category 3',
            'keyword' => 'Keyword',
            'committee' => 'Committee',
            'app_ord_no' => 'App/Ord No.',
            'amount' => 'Amount',
            'municipality_id' => 'Municipality',
            'province' => 'Province-wide',
            'pdf_path' => 'PDF (local)',
            'sp_pdf_url' => 'PDF URL',
            'document_type' => 'Document Type',
        ];
    }

    public function formatSnapshotDisplayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'province') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        if ($field === 'date_approved') {
            return \Illuminate\Support\Carbon::parse($value)->format('M j, Y');
        }

        if ($field === 'amount') {
            return number_format((int) $value);
        }

        if ($field === 'pdf_path') {
            return basename((string) $value);
        }

        if ($field === 'department_id') {
            return Department::query()->whereKey((int) $value)->value('description') ?: (string) $value;
        }

        if ($field === 'category_id') {
            return Category::query()->whereKey((int) $value)->value('description') ?: (string) $value;
        }

        if ($field === 'category2_id') {
            return Category2::query()->whereKey((int) $value)->value('description') ?: (string) $value;
        }

        if ($field === 'category3_id') {
            return Category3::query()->whereKey((int) $value)->value('description') ?: (string) $value;
        }

        if ($field === 'category4_id') {
            return Category4::query()->whereKey((int) $value)->value('description') ?: (string) $value;
        }

        if ($field === 'municipality_id') {
            return Municipality::query()->whereKey((int) $value)->value('description') ?: (string) $value;
        }

        return (string) $value;
    }

    /**
     * Ensure the current version snapshot still points at the live PDF before a replacement upload.
     */
    public function preservePdfInCurrentVersion(Resolution $resolution, ?int $userId = null): void
    {
        if ($resolution->versions()->doesntExist()) {
            $this->recordInitialVersion($resolution, $userId);

            return;
        }

        /** @var ResolutionVersion|null $current */
        $current = $resolution->versions()->where('version_no', $resolution->current_version_no)->first()
            ?? $resolution->versions()->orderByDesc('version_no')->first();

        if ($current === null) {
            return;
        }

        $snapshot = $current->snapshot ?? [];
        $changed = false;

        foreach (['pdf_path', 'sp_pdf_url'] as $field) {
            $live = $resolution->getAttribute($field);

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
        Resolution $resolution,
        ?int $userId = null,
        string $reason = 'encoded',
    ): ResolutionVersion {
        return $this->createVersion($resolution, $reason, $userId);
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     */
    public function recordVersionIfChanged(
        Resolution $resolution,
        array $originalAttributes,
        ?int $userId = null,
        ?string $forcedReason = null,
    ): ?ResolutionVersion {
        if (! $this->hasVersionableChanges($originalAttributes, $resolution)) {
            return null;
        }

        return $this->createVersion(
            $resolution,
            $forcedReason ?? $this->inferChangeReason($originalAttributes, $resolution),
            $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     */
    public function hasVersionableChanges(array $originalAttributes, Resolution $resolution): bool
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            $before = $this->normalizeSnapshotValue($field, $originalAttributes[$field] ?? null);
            $after = $this->normalizeSnapshotValue($field, $resolution->getAttribute($field));

            if ($before !== $after) {
                return true;
            }
        }

        return false;
    }

    public function createVersion(Resolution $resolution, string $reason, ?int $userId = null): ResolutionVersion
    {
        $nextVersion = (int) ($resolution->versions()->max('version_no') ?? 0) + 1;

        $version = ResolutionVersion::create([
            'resolution_id' => $resolution->id,
            'version_no' => $nextVersion,
            'change_reason' => $reason,
            'snapshot' => $this->snapshotFrom($resolution),
            'created_by' => $userId,
        ]);

        $resolution->forceFill(['current_version_no' => $nextVersion])->saveQuietly();

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFrom(Resolution $resolution): array
    {
        $snapshot = [];

        foreach (self::VERSIONED_FIELDS as $field) {
            $value = $resolution->getAttribute($field);

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
    public function changedFields(?ResolutionVersion $previous, ResolutionVersion $current): array
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

    public function deleteVersion(ResolutionVersion $version): void
    {
        $resolution = $version->resolution;

        if ($resolution->versions()->count() <= 1) {
            throw new \RuntimeException('Cannot delete the only remaining version.');
        }

        $wasCurrent = $version->version_no === $resolution->current_version_no;

        DB::transaction(function () use ($version, $resolution, $wasCurrent): void {
            $version->delete();

            if (! $wasCurrent) {
                return;
            }

            /** @var ResolutionVersion|null $replacement */
            $replacement = $resolution->versions()->orderByDesc('version_no')->first();

            if ($replacement === null) {
                return;
            }

            $this->applySnapshotToResolution($resolution, $replacement->snapshot ?? []);
            $resolution->forceFill(['current_version_no' => $replacement->version_no])->saveQuietly();
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function applySnapshotToResolution(Resolution $resolution, array $snapshot): void
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            if (! array_key_exists($field, $snapshot)) {
                continue;
            }

            $value = $snapshot[$field];

            if ($value === null || $value === '') {
                $resolution->setAttribute($field, $field === 'province' ? false : null);

                continue;
            }

            if (in_array($field, [
                'series',
                'department_id',
                'category_id',
                'category2_id',
                'category3_id',
                'category4_id',
                'municipality_id',
                'amount',
            ], true)) {
                $resolution->setAttribute($field, (int) $value);

                continue;
            }

            if ($field === 'province') {
                $resolution->setAttribute($field, filter_var($value, FILTER_VALIDATE_BOOLEAN));

                continue;
            }

            $resolution->setAttribute($field, $value);
        }

        $resolution->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $before
     */
    protected function inferChangeReason(array $before, Resolution $after): string
    {
        $titleChanged = $this->normalizeSnapshotValue('resolution_title', $before['resolution_title'] ?? null)
            !== $this->normalizeSnapshotValue('resolution_title', $after->getAttribute('resolution_title'));
        $pdfChanged = $this->anyFieldChanged($before, $after, ['pdf_path', 'sp_pdf_url']);

        if ($titleChanged && ! $pdfChanged && ! $this->anyFieldChanged($before, $after, array_diff(
            self::VERSIONED_FIELDS,
            ['resolution_title', 'pdf_path', 'sp_pdf_url'],
        ))) {
            return 'title';
        }

        if ($pdfChanged && ! $titleChanged && ! $this->anyFieldChanged($before, $after, array_diff(
            self::VERSIONED_FIELDS,
            ['pdf_path', 'sp_pdf_url'],
        ))) {
            return 'pdf';
        }

        return 'general';
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  list<string>  $fields
     */
    protected function anyFieldChanged(array $before, Resolution $after, array $fields): bool
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

        if ($field === 'province') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        return (string) $value;
    }
}
