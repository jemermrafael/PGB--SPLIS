<?php

namespace App\Services;

use App\Enums\OrdinancePublicationStatus;
use App\Models\Ordinance;
use App\Support\XlsxSheetReader;
use Carbon\Carbon;

class OrdinanceXlsxImporter
{
    public function __construct(
        protected XlsxSheetReader $reader,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(string $path, string $sheetName, bool $dryRun = false, ?int $seriesYear = null): array
    {
        $seriesYear ??= (int) config('ordinances.default_series_year', (int) now()->format('Y'));

        $sheet = $this->reader->readSheet($path, $sheetName);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($sheet['rows'] as $excelRow => $row) {
            $payload = $this->mapRow(
                $row,
                (int) $excelRow,
                $sheet['hyperlinks'],
                $sheet['fill_colors'] ?? [],
                $seriesYear,
            );

            if ($payload === null) {
                $stats['skipped']++;

                continue;
            }

            if ($dryRun) {
                $stats['created']++;

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

        return $stats;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, string>  $hyperlinks
     * @param  array<string, string>  $fillColors
     * @return array<string, mixed>|null
     */
    protected function mapRow(array $row, int $excelRow, array $hyperlinks, array $fillColors, int $seriesYear): ?array
    {
        $ordinanceNo = $this->parseOrdinanceNumber($row[0] ?? '');

        if ($ordinanceNo === null) {
            return null;
        }

        $subject = trim((string) ($row[1] ?? ''));

        if ($this->isHeaderOrLegendRow($row, $subject)) {
            return null;
        }

        if ($subject === '' && ! $this->rowHasMeaningfulData($row)) {
            return null;
        }

        return [
            'ordinance_no' => $ordinanceNo,
            'series_year' => $seriesYear,
            'subject' => $subject !== '' ? $subject : null,
            'publication_status' => $this->publicationStatusFromFillColor($fillColors["A{$excelRow}"] ?? null)?->value,
            'pdf_url' => $this->hyperlink($hyperlinks, "A{$excelRow}"),
            'date_enacted' => $this->parseDate($row[2] ?? null),
            'date_approved' => $this->parseDate($row[3] ?? null),
            'date_posted' => $this->parseDate($row[4] ?? null),
            'date_published_newspaper' => $this->parseDate($row[5] ?? null),
            'effectivity_date' => $this->parseDate($row[6] ?? null),
            'mov_bulletin' => $this->nullableText($row[7] ?? null),
            'mov_bulletin_url' => $this->hyperlink($hyperlinks, "H{$excelRow}"),
            'mov_certification' => $this->nullableString($row[8] ?? null, 200),
            'mov_certification_url' => $this->hyperlink($hyperlinks, "I{$excelRow}"),
            'mov_newspaper' => $this->nullableString($row[9] ?? null, 200),
            'mov_newspaper_url' => $this->hyperlink($hyperlinks, "J{$excelRow}"),
            'implementing_bodies' => $this->nullableText($row[10] ?? null),
            'classification' => $this->nullableString($row[11] ?? null, 100),
            'mandate_ppa' => $this->nullableString($row[12] ?? null, 100),
            'remarks' => $this->nullableText($row[13] ?? null),
        ];
    }

    /**
     * @param  array<string, string>  $hyperlinks
     */
    protected function publicationStatusFromFillColor(?string $color): ?OrdinancePublicationStatus
    {
        return OrdinancePublicationStatus::fromFillColor($color);
    }

    protected function hyperlink(array $hyperlinks, string $cellReference): ?string
    {
        $url = trim($hyperlinks[strtoupper($cellReference)] ?? '');

        return $url !== '' ? mb_substr($url, 0, 500) : null;
    }

    /**
     * @param  list<string>  $row
     */
    protected function isHeaderOrLegendRow(array $row, string $subject): bool
    {
        $firstCell = strtolower(trim((string) ($row[0] ?? '')));

        if (in_array($firstCell, ['ord no.', 'number', 'legends:', 'agenda headers'], true)) {
            return true;
        }

        $normalizedSubject = strtolower($subject);

        if (in_array($normalizedSubject, ['subject', 'published', 'for publication', 'ordinance', 'resolution'], true)) {
            return true;
        }

        if ($subject !== '' && ! str_contains($normalizedSubject, 'ordinance') && ! $this->rowHasMeaningfulData($row)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $row
     */
    protected function rowHasMeaningfulData(array $row): bool
    {
        foreach (array_slice($row, 2) as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
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

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
