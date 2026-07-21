<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AgendaItemVersion;
use Illuminate\Support\Facades\DB;

class AgendaVersionService
{
    /**
     * @var list<string>
     */
    public const VERSIONED_FIELDS = [
        'tracking_no',
        'request_pdf_url',
        'request_pdf_path',
        'date_received',
        'time_received',
        'prescribed_days',
        'status',
        'sender',
        'title',
        'is_urgent_request',
        'committee_referred',
        'date_of_referral',
        'date_of_committee_meeting',
        'committee_meeting_minutes',
        'outcome',
        'committee_report_url',
        'date_passed',
        'date_signed_by_gov',
        'reso_ord_ao_no',
        'reso_ord_ao_series',
        'reso_ord_ao_type',
        'reso_ord_ao_url',
        'resolution_title',
        'journal_url',
        'minutes_url',
        'remarks',
    ];

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'tracking_no' => 'Tracking No.',
            'request_pdf_url' => 'Request PDF URL',
            'request_pdf_path' => 'Request PDF (local)',
            'date_received' => 'Date Received',
            'time_received' => 'Time Received',
            'prescribed_days' => 'Prescribed Days',
            'status' => 'Status',
            'sender' => 'Sender',
            'title' => 'Title',
            'is_urgent_request' => 'Urgent Request',
            'committee_referred' => 'Committee Referred',
            'date_of_referral' => 'Date of Referral',
            'date_of_committee_meeting' => 'Date of Committee Meeting',
            'committee_meeting_minutes' => 'Committee Meeting Minutes',
            'outcome' => 'Outcome',
            'committee_report_url' => 'Committee Report',
            'date_passed' => 'Date Passed',
            'date_signed_by_gov' => 'Date Signed by Gov',
            'reso_ord_ao_no' => 'Provincial Output No.',
            'reso_ord_ao_series' => 'Provincial Output Series',
            'reso_ord_ao_type' => 'Provincial Output Type',
            'reso_ord_ao_url' => 'Provincial Output PDF',
            'resolution_title' => 'Resolution Title',
            'journal_url' => 'Journal URL',
            'minutes_url' => 'Minutes URL',
            'remarks' => 'Remarks',
        ];
    }

    public function formatSnapshotDisplayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'status') {
            return config('agenda.statuses.'.$value, (string) $value);
        }

        if ($field === 'reso_ord_ao_type') {
            return config('agenda.measure_types.'.$value, (string) $value);
        }

        if ($field === 'is_urgent_request') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        if ($field === 'time_received') {
            return \Illuminate\Support\Carbon::parse($value)->format('g:i A');
        }

        if (in_array($field, [
            'date_received',
            'date_of_referral',
            'date_of_committee_meeting',
            'date_passed',
            'date_signed_by_gov',
        ], true)) {
            return \Illuminate\Support\Carbon::parse($value)->format('M j, Y');
        }

        if ($field === 'request_pdf_path') {
            return basename((string) $value);
        }

        if (str_ends_with($field, '_url')) {
            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * Create v1 snapshots for agenda items that have no version rows yet.
     *
     * @return int Number of versions created
     */
    public function backfillMissingInitialVersions(): int
    {
        $created = 0;

        AgendaItem::query()
            ->withTrashed()
            ->whereDoesntHave('versions')
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$created): void {
                foreach ($items as $agenda) {
                    $this->recordInitialVersion($agenda, $agenda->created_by);
                    $created++;
                }
            });

        return $created;
    }

    /**
     * Ensure the current version snapshot still points at the live request PDF
     * before a replacement upload, so older versions keep a viewable file.
     */
    public function preserveRequestPdfInCurrentVersion(AgendaItem $agenda, ?int $userId = null): void
    {
        if ($agenda->versions()->doesntExist()) {
            $this->recordInitialVersion($agenda, $userId);

            return;
        }

        /** @var AgendaItemVersion|null $current */
        $current = $agenda->versions()->where('version_no', $agenda->current_version_no)->first()
            ?? $agenda->versions()->orderByDesc('version_no')->first();

        if ($current === null) {
            return;
        }

        $snapshot = $current->snapshot ?? [];
        $changed = false;

        foreach (['request_pdf_path', 'request_pdf_url'] as $field) {
            $live = $agenda->getAttribute($field);

            if (! filled($snapshot[$field] ?? null) && filled($live)) {
                $snapshot[$field] = $live instanceof \DateTimeInterface
                    ? $live->format('Y-m-d')
                    : $live;
                $changed = true;
            }
        }

        if ($changed) {
            $current->forceFill(['snapshot' => $snapshot])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return list<array{field: string, label: string, left: ?string, right: ?string, changed: bool}>
     */
    public function compareSnapshots(array $left, array $right): array
    {
        $rows = [];

        foreach (self::fieldLabels() as $field => $label) {
            $leftValue = $this->formatSnapshotDisplayValue($field, $left[$field] ?? null);
            $rightValue = $this->formatSnapshotDisplayValue($field, $right[$field] ?? null);
            $changed = $this->normalizeSnapshotValue($field, $left[$field] ?? null)
                !== $this->normalizeSnapshotValue($field, $right[$field] ?? null);

            $rows[] = [
                'field' => $field,
                'label' => $label,
                'left' => $leftValue,
                'right' => $rightValue,
                'changed' => $changed,
            ];
        }

        return $rows;
    }

    public function recordInitialVersion(AgendaItem $agenda, ?int $userId = null): AgendaItemVersion
    {
        return $this->createVersion($agenda, 'encoded', $userId);
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     */
    public function recordVersionIfChanged(AgendaItem $agenda, array $originalAttributes, ?int $userId = null): ?AgendaItemVersion
    {
        if (! $this->hasVersionableChanges($originalAttributes, $agenda)) {
            return null;
        }

        return $this->createVersion(
            $agenda,
            $this->inferChangeReason($originalAttributes, $agenda),
            $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $originalAttributes
     */
    public function hasVersionableChanges(array $originalAttributes, AgendaItem $agenda): bool
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            $before = $this->normalizeSnapshotValue($field, $originalAttributes[$field] ?? null);
            $after = $this->normalizeSnapshotValue($field, $agenda->getAttribute($field));

            if ($before !== $after) {
                return true;
            }
        }

        return false;
    }

    public function createVersion(AgendaItem $agenda, string $reason, ?int $userId = null): AgendaItemVersion
    {
        $nextVersion = (int) ($agenda->versions()->max('version_no') ?? 0) + 1;

        $version = AgendaItemVersion::create([
            'agenda_item_id' => $agenda->id,
            'version_no' => $nextVersion,
            'change_reason' => $reason,
            'snapshot' => $this->snapshotFrom($agenda),
            'created_by' => $userId,
        ]);

        $agenda->forceFill(['current_version_no' => $nextVersion])->saveQuietly();

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFrom(AgendaItem $agenda): array
    {
        $snapshot = [];

        foreach (self::VERSIONED_FIELDS as $field) {
            $value = $agenda->getAttribute($field);

            if ($value instanceof \DateTimeInterface) {
                $snapshot[$field] = $value->format($field === 'time_received' ? 'H:i:s' : 'Y-m-d');
            } else {
                $snapshot[$field] = $value;
            }
        }

        return $snapshot;
    }

    public function deleteVersion(AgendaItemVersion $version): void
    {
        $agenda = $version->agendaItem;

        if ($agenda->versions()->count() <= 1) {
            throw new \RuntimeException('Cannot delete the only remaining version.');
        }

        $wasCurrent = $version->version_no === $agenda->current_version_no;

        DB::transaction(function () use ($version, $agenda, $wasCurrent): void {
            $version->delete();

            if (! $wasCurrent) {
                return;
            }

            /** @var AgendaItemVersion|null $replacement */
            $replacement = $agenda->versions()->orderByDesc('version_no')->first();

            if ($replacement === null) {
                return;
            }

            $this->applySnapshotToAgenda($agenda, $replacement->snapshot ?? []);
            $agenda->forceFill(['current_version_no' => $replacement->version_no])->saveQuietly();
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function applySnapshotToAgenda(AgendaItem $agenda, array $snapshot): void
    {
        foreach (self::VERSIONED_FIELDS as $field) {
            if (! array_key_exists($field, $snapshot)) {
                continue;
            }

            $value = $snapshot[$field];

            if ($value === null || $value === '') {
                $agenda->setAttribute($field, null);

                continue;
            }

            if (in_array($field, ['date_received', 'date_of_referral', 'date_of_committee_meeting', 'date_passed', 'date_signed_by_gov'], true)) {
                $agenda->setAttribute($field, $value);

                continue;
            }

            if ($field === 'prescribed_days' || $field === 'reso_ord_ao_series') {
                $agenda->setAttribute($field, (int) $value);

                continue;
            }

            $agenda->setAttribute($field, $value);
        }

        $agenda->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $before
     */
    protected function inferChangeReason(array $before, AgendaItem $after): string
    {
        $referralFields = ['committee_referred', 'date_of_referral'];
        if ($this->anyFieldChanged($before, $after, $referralFields)) {
            return 'referral';
        }

        $committeeMeetingFields = [
            'date_of_committee_meeting',
            'committee_meeting_minutes',
            'outcome',
            'committee_report_url',
        ];
        if ($this->anyFieldChanged($before, $after, $committeeMeetingFields)) {
            return 'committee_meeting';
        }

        $outputFields = [
            'reso_ord_ao_no',
            'reso_ord_ao_series',
            'reso_ord_ao_type',
            'reso_ord_ao_url',
            'resolution_title',
            'date_passed',
            'date_signed_by_gov',
            'journal_url',
            'minutes_url',
            'remarks',
        ];
        if ($this->anyFieldChanged($before, $after, $outputFields)) {
            return 'output';
        }

        $sessionFields = ['title', 'request_pdf_url', 'request_pdf_path', 'journal_url', 'minutes_url'];
        if ($this->anyFieldChanged($before, $after, $sessionFields)) {
            return 'session';
        }

        return 'general';
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  list<string>  $fields
     */
    protected function anyFieldChanged(array $before, AgendaItem $after, array $fields): bool
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
            return $value->format($field === 'time_received' ? 'H:i:s' : 'Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
