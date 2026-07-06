<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Support\AgendaDeadline;
use Carbon\Carbon;

class AgendaCsvImporter
{
    public function __construct(
        protected CsvExportReader $csv,
    ) {}

    /**
     * @return array{
     *     agenda_file: string,
     *     links_file: ?string,
     *     imported: int,
     *     updated: int,
     *     skipped: int,
     *     total: int
     * }
     */
    public function sync(?string $csvPath = null, ?string $linksPath = null, bool $dryRun = false): array
    {
        $csvPath = $csvPath ?: config('agenda.csv_path');
        $linksPath = $linksPath ?: config('agenda.csv_links_path');

        if (! is_file($csvPath)) {
            throw new \RuntimeException('Agenda CSV not found: '.$csvPath);
        }

        $links = $this->loadLinksMap($this->resolveLinksPath($csvPath, $linksPath));
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $total = 0;

        foreach ($this->csv->indexedRows($csvPath) as $indexed) {
            $row = $indexed['assoc'];
            $columns = $indexed['columns'];
            $trackingNo = $this->trackingNoFromRow($row, $columns);
            if ($trackingNo === null) {
                continue;
            }

            $total++;
            $linkRow = array_merge(
                $links[$trackingNo] ?? [],
                array_filter($this->embeddedLinksFromColumns($columns)),
            );

            $payload = $this->mapRow($row, $linkRow, $columns);

            try {
                $existing = AgendaItem::query()->where('tracking_no', $trackingNo)->first();
                if ($existing) {
                    if (! $dryRun) {
                        $existing->update($payload);
                    }
                    $updated++;
                } else {
                    if (! $dryRun) {
                        AgendaItem::create(array_merge($payload, ['tracking_no' => $trackingNo]));
                    }
                    $imported++;
                }
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return [
            'agenda_file' => $csvPath,
            'links_file' => $this->resolveLinksPath($csvPath, $linksPath),
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * @return array{imported: int, updated: int, skipped: int, total: int}
     */
    public function import(?string $csvPath = null, ?string $linksPath = null): array
    {
        $stats = $this->sync($csvPath, $linksPath);

        return [
            'imported' => $stats['imported'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
            'total' => $stats['total'],
        ];
    }

    protected function resolveLinksPath(string $csvPath, ?string $linksPath): ?string
    {
        $linksPath = $linksPath ?: config('agenda.csv_links_path');

        if (! is_file((string) $linksPath)) {
            return null;
        }

        if (realpath($csvPath) === realpath((string) $linksPath)) {
            return null;
        }

        return $linksPath;
    }

    /**
     * PDF URLs embedded in Agenda4-style exports (tracking col + request PDF col).
     *
     * @param  list<string|null>  $columns
     * @return array<string, string>
     */
    protected function embeddedLinksFromColumns(array $columns): array
    {
        return array_filter([
            'request_pdf_url' => $this->urlOrNull($columns[1] ?? null),
            'committee_report_url' => $this->firstUrl($columns, [16, 15]),
            'reso_ord_ao_url' => $this->firstUrl($columns, [20]),
            'journal_url' => $this->firstUrl($columns, [23]),
            'minutes_url' => $this->firstUrl($columns, [25]),
        ]);
    }

    /**
     * @param  list<string|null>  $columns
     * @param  list<int>  $indices
     */
    protected function firstUrl(array $columns, array $indices): ?string
    {
        foreach ($indices as $index) {
            $url = $this->urlOrNull($columns[$index] ?? null);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, string|null>  $links
     * @param  list<string|null>  $columns
     * @return array<string, mixed>
     */
    protected function mapRow(array $row, array $links, array $columns = []): array
    {
        $dateReceived = $this->parseDate($this->cell($row, ['Date Received', 'date_received']));
        $datePassed = $this->parseDate($this->cell($row, ['Date passed', 'date_passed']));
        $dateSigned = $this->parseDate($this->cell($row, ['Date signed by Gov.', 'date_signed_by_gov']));
        $prescribed = $this->parseInt($this->cell($row, ['Prescribed Dates', 'prescribed_days']));
        $status = $this->mapStatus($this->cell($row, ['Status', 'status']), $prescribed);
        $resolutionTitle = $this->cell($row, ['Resolution Title', 'resolution_title']);

        $item = new AgendaItem([
            'request_pdf_url' => $links['request_pdf_url'] ?? $this->urlOrNull($this->cell($row, ['request_pdf_url'])),
            'date_received' => $dateReceived,
            'time_received' => $this->parseTime($this->cell($row, ['Time Received', 'time_received'])),
            'prescribed_days' => $prescribed,
            'status' => $status,
            'sender' => $this->cell($row, ['Sender', 'sender']),
            'title' => $this->cell($row, ['Title', 'title']),
            'committee_referred' => $this->cell($row, ['Committee Referred', 'committee_referred']),
            'date_of_referral' => $this->parseDate($this->cell($row, ['Date of Referral', 'date_of_referral']))
                ?? $this->dateOrNull($columns[11] ?? null),
            'date_of_committee_meeting' => $this->parseDate($this->cell($row, ['Date of Commitee Meeting', 'Date of Committee Meeting', 'date_of_committee_meeting'])),
            'committee_meeting_minutes' => $this->cell($row, ['Com. Meeting Minutes', 'committee_meeting_minutes']),
            'outcome' => $this->cell($row, ['Outcome', 'outcome']),
            'committee_report_url' => $links['committee_report_url']
                ?? $this->urlOrNull($this->cell($row, ['Committee Report Link', 'committee_report_url'])),
            'date_passed' => $datePassed,
            'date_signed_by_gov' => $dateSigned,
            'reso_ord_ao_no' => $this->normalizeResoNo($this->cell($row, ['Reso./Ord./AO No.', 'reso_ord_ao_no'])),
            'reso_ord_ao_series' => AgendaDeadline::inferSeries(
                $datePassed ? Carbon::parse($datePassed) : null,
                $dateSigned ? Carbon::parse($dateSigned) : null,
                $dateReceived ? Carbon::parse($dateReceived) : null,
            ),
            'reso_ord_ao_type' => null,
            'reso_ord_ao_url' => $links['reso_ord_ao_url'] ?? $this->urlOrNull($this->cell($row, ['reso_ord_ao_url'])),
            'resolution_title' => $resolutionTitle,
            'journal_url' => $links['journal_url'] ?? $this->urlOrNull($this->cell($row, ['Journal of Proceedings', 'journal_url'])),
            'minutes_url' => $links['minutes_url'] ?? $this->urlOrNull($this->cell($row, ['Minutes of Session', 'minutes_url'])),
            'remarks' => $this->cell($row, ['Status/ Remarks', 'remarks']),
        ]);

        AgendaDeadline::apply($item);

        $daysLeftCsv = trim((string) ($this->cell($row, ['Days Left', 'days_left']) ?? ''));
        if ($status === AgendaItem::STATUS_DONE && strcasecmp($daysLeftCsv, 'Accomplished') === 0) {
            $item->days_left_label = 'Accomplished';
        }

        return $this->sanitizePayload($item->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $payload[$key] = $this->normalizeText($value) ?? '';
            }
        }

        return $payload;
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    protected function loadLinksMap(?string $path): array
    {
        if (! $path || ! is_file($path)) {
            return [];
        }

        $map = [];
        foreach ($this->csv->indexedRows($path) as $row) {
            $trackingNo = $this->trackingNoFromRow($row['assoc'], $row['columns']);
            if (! $trackingNo) {
                continue;
            }

            $cols = $row['columns'];
            $assoc = $row['assoc'];

            $map[$trackingNo] = [
                'request_pdf_url' => $this->urlOrNull($cols[1] ?? null)
                    ?? $this->urlOrNull($this->cell($assoc, ['request_pdf_url', 'Request PDF', 'request pdf'])),
                'committee_report_url' => $this->urlOrNull($cols[16] ?? null)
                    ?? $this->urlOrNull($this->cell($assoc, ['committee_report_url', 'Committee Report Link'])),
                'reso_ord_ao_url' => $this->urlOrNull($cols[20] ?? null)
                    ?? $this->urlOrNull($this->cell($assoc, ['reso_ord_ao_url', 'Reso PDF'])),
                'journal_url' => $this->urlOrNull($cols[23] ?? null)
                    ?? $this->urlOrNull($this->cell($assoc, ['journal_url', 'Journal of Proceedings'])),
                'minutes_url' => $this->urlOrNull($cols[25] ?? null)
                    ?? $this->urlOrNull($this->cell($assoc, ['minutes_url', 'Minutes of Session'])),
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string|null>  $columns
     */
    protected function trackingNoFromRow(array $row, array $columns): ?string
    {
        $trackingNo = $this->normalizeText(
            $this->cell($row, [' ', '', 'tracking_no', 'no', '#'])
                ?? ($columns[0] ?? null),
        );

        if ($trackingNo === null || trim($trackingNo) === '') {
            return null;
        }

        $trackingNo = trim($trackingNo);
        $digits = ltrim($trackingNo, '0') ?: '0';

        if (! ctype_digit($digits)) {
            return null;
        }

        return str_pad($digits, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $keys
     */
    protected function cell(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) ($row[$key] ?? '')) !== '') {
                return $this->normalizeText(trim((string) $row[$key]));
            }
        }

        return null;
    }

    protected function normalizeText(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = (string) @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            if ($converted !== false) {
                $value = (string) @iconv('UTF-8', 'UTF-8//IGNORE', $converted);
            }
        }

        if (function_exists('mb_scrub')) {
            $value = mb_scrub($value, 'UTF-8');
        }

        return str_replace(
            [
                "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}",
                "\u{2013}", "\u{2014}",
                "\x93", "\x94", "\x91", "\x92", "\x96", "\x97",
            ],
            ['"', '"', "'", "'", '-', '-', '"', '"', "'", "'", '-', '-'],
            $value,
        );
    }

    protected function mapStatus(?string $value, ?int $prescribed): string
    {
        $value = strtolower(trim((string) $value));

        return match (true) {
            $value === 'done' => AgendaItem::STATUS_DONE,
            $value === 'lapsed' => AgendaItem::STATUS_LAPSED,
            str_contains($value, 'no due') => AgendaItem::STATUS_NO_DUE_DATE,
            $prescribed === 0 => AgendaItem::STATUS_NO_DUE_DATE,
            default => AgendaItem::STATUS_PENDING,
        };
    }

    protected function dateOrNull(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $this->parseDate($value);
    }

    protected function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (int) $value;
    }

    protected function normalizeResoNo(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $value;
    }

    protected function urlOrNull(?string $value): ?string
    {
        $value = $this->normalizeText($value) ?? '';
        if ($value === '' || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $value;
    }
}
