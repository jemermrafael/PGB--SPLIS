<?php

namespace App\Services;

use App\Models\AppropriationOrdinance;
use Carbon\Carbon;

class AppropriationOrdinanceCsvImporter
{
    public function __construct(
        protected CsvExportReader $csv,
    ) {}

    /**
     * @return array{processed: int, created: int, updated: int, skipped: int}
     */
    public function import(string $path, bool $dryRun = false, ?int $seriesYear = null): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("CSV not found: {$path}");
        }

        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($this->csv->indexedRows($path) as $row) {
            $stats['processed']++;

            $payload = $this->mapRow($row['columns'], $seriesYear);

            if ($payload === null) {
                $stats['skipped']++;

                continue;
            }

            if ($dryRun) {
                $stats['created']++;

                continue;
            }

            $record = AppropriationOrdinance::query()->updateOrCreate(
                [
                    'ordinance_no' => $payload['ordinance_no'],
                    'series_year' => $payload['series_year'],
                ],
                $payload,
            );

            if ($record->wasRecentlyCreated) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * @param  list<string|null>  $columns
     * @return array<string, mixed>|null
     */
    protected function mapRow(array $columns, ?int $defaultSeriesYear): ?array
    {
        $dateReceived = $this->parseDate($columns[0] ?? null);
        $subject = trim((string) ($columns[1] ?? ''));
        $ordinanceNo = $this->parseOrdinanceNumber((string) ($columns[2] ?? ''));
        $pdfUrl = $this->nullableUrl($columns[3] ?? null);
        $datePassed = $this->parseDate($columns[4] ?? null);
        $dateApproved = $this->parseDate($columns[5] ?? null);

        if ($this->isHeaderRow($columns, $subject)) {
            return null;
        }

        if ($ordinanceNo === null || $subject === '') {
            return null;
        }

        $seriesYear = $defaultSeriesYear
            ?? $this->inferSeriesYear($dateReceived, $datePassed, $dateApproved);

        return [
            'date_received' => $dateReceived,
            'subject' => $subject,
            'ordinance_no' => $ordinanceNo,
            'series_year' => $seriesYear,
            'date_passed' => $datePassed,
            'date_approved' => $dateApproved,
            'pdf_url' => $pdfUrl,
        ];
    }

    /**
     * @param  list<string|null>  $columns
     */
    protected function isHeaderRow(array $columns, string $subject): bool
    {
        $first = strtolower(trim((string) ($columns[0] ?? '')));

        if (in_array($first, ['date received', 'date'], true)) {
            return true;
        }

        return strtolower($subject) === 'titled';
    }

    protected function parseOrdinanceNumber(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $number = (int) $value;

            return $number > 0 ? $number : null;
        }

        if (preg_match('/\b(\d{1,5})\b/', $value, $matches)) {
            $number = (int) $matches[1];

            return $number > 0 ? $number : null;
        }

        return null;
    }

    protected function parseDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function inferSeriesYear(?string $dateReceived, ?string $datePassed, ?string $dateApproved): int
    {
        foreach ([$dateReceived, $datePassed, $dateApproved] as $date) {
            if ($date !== null) {
                return (int) Carbon::parse($date)->format('Y');
            }
        }

        return (int) config('appropriation_ordinances.default_series_year', (int) now()->format('Y'));
    }

    protected function nullableUrl(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 500);
    }
}
