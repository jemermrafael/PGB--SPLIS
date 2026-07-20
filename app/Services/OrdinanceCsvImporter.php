<?php

namespace App\Services;

use App\Enums\OrdinancePublicationStatus;
use App\Models\Ordinance;
use Carbon\Carbon;

class OrdinanceCsvImporter
{
    /** @var array<string, int>|null */
    protected ?array $columnMap = null;

    public function __construct(
        protected CsvExportReader $csv,
    ) {}

    /**
     * @return array{
     *     directory: string,
     *     csv_file: string,
     *     processed: int,
     *     created: int,
     *     updated: int,
     *     skipped: int
     * }
     */
    public function sync(
        ?string $directory = null,
        bool $dryRun = false,
        ?string $csvFilePath = null,
        ?int $seriesYear = null,
    ): array {
        if ($csvFilePath !== null) {
            if (! is_file($csvFilePath)) {
                throw new \RuntimeException("Uploaded CSV not found: {$csvFilePath}");
            }

            $csvFile = $csvFilePath;
            $directory = $this->csv->resolveDirectory($directory ?: config('ordinances.csv_export_path'));
        } else {
            $directory = rtrim($directory ?: config('ordinances.csv_export_path'), DIRECTORY_SEPARATOR);

            if (! is_dir($directory)) {
                throw new \RuntimeException("CSV directory not found: {$directory}");
            }

            $csvFile = $this->csv->findNewest($directory, (string) config('ordinances.csv_prefix', 'Ordinances-'));

            if ($csvFile === null) {
                throw new \RuntimeException('No Ordinances-*.csv file found.');
            }
        }

        $this->columnMap = $this->readColumnMap($csvFile);

        $stats = [
            'directory' => $directory,
            'csv_file' => $csvFile,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($this->csv->indexedRows($csvFile) as $row) {
            $stats['processed']++;

            $payload = $this->mapRow($row['columns'], $seriesYear);

            if ($payload === null) {
                $stats['skipped']++;

                continue;
            }

            if ($dryRun) {
                $exists = Ordinance::query()
                    ->where('ordinance_no', $payload['ordinance_no'])
                    ->where('series_year', $payload['series_year'])
                    ->exists();

                if ($exists) {
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }

                continue;
            }

            $ordinance = Ordinance::query()->updateOrCreate(
                [
                    'ordinance_no' => $payload['ordinance_no'],
                    'series_year' => $payload['series_year'],
                ],
                $payload,
            );

            if ($ordinance->wasRecentlyCreated) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        $this->columnMap = null;

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    protected function readColumnMap(string $csvFile): array
    {
        $handle = fopen($csvFile, 'r');

        if ($handle === false) {
            return $this->defaultColumnMap();
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if ($headers === false || $headers === []) {
            return $this->defaultColumnMap();
        }

        return $this->buildColumnMap($headers);
    }

    /**
     * Ordinances-001.csv header:
     * ORD NO., GDrive, Publish Status, SUBJECT, DATE ENACTED, DATE APPROVED,
     * POSTED IN CONSPICUOUS PLACES, PUBLISHED IN NEWSPAPER, EFFECTIVITY DATE,
     * BULLETIN, BULLETIN (GDrive), CERTIFICATION, CERTIFICATION GDrive,
     * NEWSPAPER, NEWSPAPER Gdrive, IMPLEMENTING BODIES…, CLASSIFICATION, MANDATE/PPA, REMARKS
     *
     * @param  list<string|null>  $headers
     * @return array<string, int>
     */
    protected function buildColumnMap(array $headers): array
    {
        $map = [];
        $bulletinCount = 0;

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if ($normalized === '') {
                continue;
            }

            if (in_array($normalized, ['ord no', 'ord no.', 'ordinance no', 'ordinance no.'], true)) {
                $map['ordinance_no'] = $index;

                continue;
            }

            if (in_array($normalized, ['gdrive', 'pdf', 'pdf url', 'ordinance pdf'], true)) {
                $map['pdf_url'] = $index;

                continue;
            }

            if (in_array($normalized, ['publish status', 'publication status', 'status'], true)) {
                $map['publication_status'] = $index;

                continue;
            }

            if ($normalized === 'subject') {
                $map['subject'] = $index;

                continue;
            }

            if (in_array($normalized, ['date enacted', 'enacted'], true)) {
                $map['date_enacted'] = $index;

                continue;
            }

            if (in_array($normalized, ['date approved', 'approved'], true)) {
                $map['date_approved'] = $index;

                continue;
            }

            if (in_array($normalized, ['posted in conspicuous places', 'date posted', 'posted'], true)) {
                $map['date_posted'] = $index;

                continue;
            }

            if (in_array($normalized, ['published in newspaper', 'newspaper publication date'], true)) {
                $map['date_published_newspaper'] = $index;

                continue;
            }

            if (in_array($normalized, ['effectivity date', 'effectivity'], true)) {
                $map['effectivity_date'] = $index;

                continue;
            }

            if ($normalized === 'bulletin') {
                $bulletinCount++;

                if ($bulletinCount === 1) {
                    $map['mov_bulletin'] = $index;
                } else {
                    $map['mov_bulletin_url'] = $index;
                }

                continue;
            }

            if (in_array($normalized, ['certification gdrive', 'certification g drive', 'certification url'], true)) {
                $map['mov_certification_url'] = $index;

                continue;
            }

            if ($normalized === 'certification') {
                $map['mov_certification'] = $index;

                continue;
            }

            if (in_array($normalized, ['newspaper gdrive', 'newspaper g drive', 'newspaper url'], true)) {
                $map['mov_newspaper_url'] = $index;

                continue;
            }

            if ($normalized === 'newspaper') {
                $map['mov_newspaper'] = $index;

                continue;
            }

            if (str_starts_with($normalized, 'implementing bodies')) {
                $map['implementing_bodies'] = $index;

                continue;
            }

            if (in_array($normalized, ['perspective classification', 'classification'], true)) {
                $map['classification'] = $index;

                continue;
            }

            if (in_array($normalized, ['mandate/ppa', 'mandate ppa', 'mandate'], true)) {
                $map['mandate_ppa'] = $index;

                continue;
            }

            if ($normalized === 'remarks') {
                $map['remarks'] = $index;
            }
        }

        return array_merge($this->defaultColumnMap(), $map);
    }

    /**
     * @return array<string, int>
     */
    protected function defaultColumnMap(): array
    {
        return [
            'ordinance_no' => 0,
            'pdf_url' => 1,
            'publication_status' => 2,
            'subject' => 3,
            'date_enacted' => 4,
            'date_approved' => 5,
            'date_posted' => 6,
            'date_published_newspaper' => 7,
            'effectivity_date' => 8,
            'mov_bulletin' => 9,
            'mov_bulletin_url' => 10,
            'mov_certification' => 11,
            'mov_certification_url' => 12,
            'mov_newspaper' => 13,
            'mov_newspaper_url' => 14,
            'implementing_bodies' => 15,
            'classification' => 16,
            'mandate_ppa' => 17,
            'remarks' => 18,
        ];
    }

    protected function normalizeHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $header) ?? ''));
    }

    /**
     * @param  list<string|null>  $columns
     * @return array<string, mixed>|null
     */
    protected function mapRow(array $columns, ?int $defaultSeriesYear): ?array
    {
        $map = $this->columnMap ?? $this->defaultColumnMap();

        $ordinanceNo = $this->parseOrdinanceNumber((string) ($columns[$map['ordinance_no']] ?? ''));

        if ($ordinanceNo === null) {
            return null;
        }

        $subject = trim((string) ($columns[$map['subject']] ?? ''));

        if ($this->isHeaderRow($columns, $subject)) {
            return null;
        }

        if ($subject === '' && ! $this->rowHasMeaningfulData($columns, $map)) {
            return null;
        }

        $dateEnacted = $this->parseDate($columns[$map['date_enacted']] ?? null);
        $dateApproved = $this->parseDate($columns[$map['date_approved']] ?? null);
        $datePosted = $this->parseDate($columns[$map['date_posted']] ?? null);
        $datePublishedNewspaper = $this->parseDate($columns[$map['date_published_newspaper']] ?? null);
        $effectivityDate = $this->parseDate($columns[$map['effectivity_date']] ?? null);

        $seriesYear = $defaultSeriesYear
            ?? $this->inferSeriesYear($dateEnacted, $dateApproved, $datePosted, $effectivityDate);

        return [
            'ordinance_no' => $ordinanceNo,
            'series_year' => $seriesYear,
            'subject' => $subject !== '' ? $subject : null,
            'publication_status' => $this->parsePublicationStatus($columns[$map['publication_status']] ?? null)?->value,
            'pdf_url' => $this->nullableUrl($columns[$map['pdf_url']] ?? null),
            'date_enacted' => $dateEnacted,
            'date_approved' => $dateApproved,
            'date_posted' => $datePosted,
            'date_published_newspaper' => $datePublishedNewspaper,
            'effectivity_date' => $effectivityDate,
            'mov_bulletin' => $this->nullableText($columns[$map['mov_bulletin']] ?? null),
            'mov_bulletin_url' => $this->nullableUrl($columns[$map['mov_bulletin_url']] ?? null),
            'mov_certification' => $this->nullableString($columns[$map['mov_certification']] ?? null, 200),
            'mov_certification_url' => $this->nullableUrl($columns[$map['mov_certification_url']] ?? null),
            'mov_newspaper' => $this->nullableString($columns[$map['mov_newspaper']] ?? null, 200),
            'mov_newspaper_url' => $this->nullableUrl($columns[$map['mov_newspaper_url']] ?? null),
            'implementing_bodies' => $this->nullableText($columns[$map['implementing_bodies']] ?? null),
            'classification' => $this->nullableString($columns[$map['classification']] ?? null, 100),
            'mandate_ppa' => $this->nullableString($columns[$map['mandate_ppa']] ?? null, 100),
            'remarks' => $this->nullableText($columns[$map['remarks']] ?? null),
        ];
    }

    /**
     * @param  list<string|null>  $columns
     */
    protected function isHeaderRow(array $columns, string $subject): bool
    {
        $first = strtolower(trim((string) ($columns[0] ?? '')));

        if (in_array($first, ['ord no.', 'ord no', 'number'], true)) {
            return true;
        }

        return strtolower($subject) === 'subject';
    }

    /**
     * @param  list<string|null>  $columns
     * @param  array<string, int>  $map
     */
    protected function rowHasMeaningfulData(array $columns, array $map): bool
    {
        foreach (['pdf_url', 'date_enacted', 'mov_bulletin', 'mov_bulletin_url'] as $key) {
            if (trim((string) ($columns[$map[$key]] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    protected function parsePublicationStatus(mixed $value): ?OrdinancePublicationStatus
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        return match ($normalized) {
            'PUBLISHED' => OrdinancePublicationStatus::Published,
            'FOR PUBLICATION', 'FOR PUB' => OrdinancePublicationStatus::ForPublication,
            default => null,
        };
    }

    protected function parseOrdinanceNumber(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
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

        if (preg_match_all('/\d{1,2}\/\d{1,2}\/\d{2,4}/', $value, $matches) && $matches[0] !== []) {
            $last = end($matches[0]);

            try {
                return Carbon::parse((string) $last)->toDateString();
            } catch (\Throwable) {
                // Fall through to whole-string parse.
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function inferSeriesYear(
        ?string $dateEnacted,
        ?string $dateApproved,
        ?string $datePosted,
        ?string $effectivityDate,
    ): int {
        foreach ([$dateEnacted, $dateApproved, $datePosted, $effectivityDate] as $date) {
            if ($date !== null) {
                return (int) Carbon::parse($date)->format('Y');
            }
        }

        return (int) config('ordinances.default_series_year', (int) now()->format('Y'));
    }

    protected function nullableUrl(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 500);
    }

    protected function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    protected function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
