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
        'date_received',
        'time_received',
        'prescribed_days',
        'status',
        'sender',
        'title',
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
        ];
        if ($this->anyFieldChanged($before, $after, $outputFields)) {
            return 'output';
        }

        $sessionFields = ['title', 'request_pdf_url', 'journal_url', 'minutes_url'];
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
