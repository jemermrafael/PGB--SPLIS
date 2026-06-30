<?php

namespace App\Services;

use App\Models\Legacy\SptrackFile;
use App\Services\CsvExportReader;
use App\Support\SptrackRecordDatetime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SptrackReader
{
    public function __construct(
        protected CsvExportReader $csv,
    ) {}

    public function canUseDatabase(): bool
    {
        try {
            DB::connection('sptrack')->getPdo();

            return DB::connection('sptrack')->getSchemaBuilder()->hasTable('Files');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function files(?string $csvPath = null): Collection
    {
        $rows = collect();
        $this->chunkFiles(500, function (array $chunk) use ($rows) {
            foreach ($chunk as $row) {
                $rows->push($row);
            }
        }, $csvPath);

        return $rows;
    }

    public function chunkFiles(int $size, callable $callback, ?string $csvPath = null, string $source = 'database'): int
    {
        if ($source === 'database' && $this->canUseDatabase()) {
            $count = 0;
            SptrackFile::query()
                ->select([
                    'FileId', 'ResNo', 'DateReceived', 'Series', 'Municipality', 'Title',
                    'ActionTaken', 'Referral', 'Agenda', 'Status', 'SPDateApproved',
                    'SPResNo', 'SPSeries', 'SPTitle', 'ConcernedAgency', 'PDFLink',
                    'PDFLinkMun', 'Keyword', 'Remarks', 'RecAdded', 'RecModified',
                ])
                ->orderBy('FileId')
                ->chunkById($size, function ($chunk) use ($callback, &$count) {
                    $mapped = $chunk->map(fn (SptrackFile $row) => $this->normalizeRow($row->toArray()))->all();
                    if ($callback($mapped) === false) {
                        return false;
                    }
                    $count += count($mapped);
                }, 'FileId');

            return $count;
        }

        return $this->chunkCsvFiles($size, $callback, $csvPath ?: $this->defaultCsvPath());
    }

    protected function chunkCsvFiles(int $size, callable $callback, string $path): int
    {
        if (! is_file($path)) {
            throw new \RuntimeException('sptrack database unavailable and CSV not found: '.$path);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV: '.$path);
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return 0;
        }

        $headers = array_map(fn ($h) => trim((string) $h, '"'), $headers);
        $batch = [];
        $count = 0;

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }

            if (isset($line[0]) && in_array(trim((string) $line[0], '"'), ['LoginId', 'Folder', 'Department', 'UserTypeId'], true)) {
                break;
            }

            if (count($line) < 10) {
                break;
            }

            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = isset($line[$i]) ? trim((string) $line[$i], '"') : null;
            }

            if (empty($assoc['FileId'])) {
                continue;
            }

            $batch[] = $this->normalizeRow($this->mapCsvRow($assoc));
            if (count($batch) >= $size) {
                if ($callback($batch) === false) {
                    $count += count($batch);
                    fclose($handle);

                    return $count;
                }
                $count += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $callback($batch);
            $count += count($batch);
        }

        fclose($handle);

        return $count;
    }

    public function defaultCsvPathExists(): bool
    {
        return is_file($this->defaultCsvPath());
    }

    protected function defaultCsvPath(): string
    {
        $candidates = [
            base_path('oldsp/Databases/SPBataan/sptrack (1).csv'),
            base_path('oldsp/Databases/SPBataan/sptrack.csv'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $candidates[0];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapCsvRow(array $row): array
    {
        return [
            'FileId' => $row['FileId'] ?? null,
            'ResNo' => $row['ResNo'] ?? null,
            'DateReceived' => $row['DateReceived'] ?? null,
            'Series' => $row['Series'] ?? null,
            'Municipality' => $row['Municipality'] ?? null,
            'Title' => $row['Title'] ?? null,
            'ActionTaken' => $row['ActionTaken'] ?? null,
            'Referral' => $row['Referral'] ?? null,
            'Agenda' => $row['Agenda'] ?? null,
            'Status' => $row['Status'] ?? null,
            'SPDateApproved' => $row['SPDateApproved'] ?? null,
            'SPResNo' => $row['SPResNo'] ?? null,
            'SPSeries' => $row['SPSeries'] ?? null,
            'SPTitle' => $row['SPTitle'] ?? null,
            'ConcernedAgency' => $row['ConcernedAgency'] ?? null,
            'PDFLink' => $row['PDFLink'] ?? null,
            'PDFLinkMun' => $row['PDFLinkMun'] ?? null,
            'Keyword' => $row['Keyword'] ?? null,
            'Remarks' => $row['Remarks'] ?? null,
            'RecAdded' => $row['RecAdded'] ?? null,
            'RecModified' => $row['RecModified'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeRow(array $row): array
    {
        return [
            'legacy_file_id' => (int) ($row['FileId'] ?? 0),
            'sp_res_no' => $this->stringOrNull($row['SPResNo'] ?? null),
            'sp_series' => $this->intOrNull($row['SPSeries'] ?? null),
            'mun_resolution_no' => $this->stringOrNull($row['ResNo'] ?? null),
            'mun_title' => $this->stringOrNull($row['Title'] ?? null),
            'mun_series' => $this->stringOrNull($row['Series'] ?? null),
            'date_received' => $this->dateOrNull($row['DateReceived'] ?? null),
            'municipality' => $this->stringOrNull($row['Municipality'] ?? null),
            'referral' => $this->stringOrNull($row['Referral'] ?? null),
            'keyword' => $this->stringOrNull($row['Keyword'] ?? null),
            'sptrack_status' => $this->stringOrNull($row['Status'] ?? null),
            'action_taken' => $this->stringOrNull($row['ActionTaken'] ?? null),
            'agenda' => $this->stringOrNull($row['Agenda'] ?? null),
            'concerned_agency' => $this->stringOrNull($row['ConcernedAgency'] ?? null),
            'remarks' => $this->stringOrNull($row['Remarks'] ?? null),
            'sp_title' => $this->stringOrNull($row['SPTitle'] ?? null),
            'sp_date_approved' => $this->dateOrNull($row['SPDateApproved'] ?? null),
            'sp_pdf_url' => $this->stringOrNull($row['PDFLink'] ?? null),
            'mun_pdf_url' => $this->stringOrNull($row['PDFLinkMun'] ?? null),
            'sp_rec_added' => $this->datetimeOrNull($row['RecAdded'] ?? null),
            'sp_rec_modified' => $this->datetimeOrNull($row['RecModified'] ?? null),
            'sp_rec_added_by' => $this->legacyUsernameOrNull($row['RecAdded'] ?? null),
            'sp_rec_modified_by' => $this->legacyUsernameOrNull($row['RecModified'] ?? null),
        ];
    }

    protected function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    protected function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function datetimeOrNull(mixed $value): ?string
    {
        return SptrackRecordDatetime::parse(is_string($value) || is_numeric($value) ? (string) $value : null);
    }

    protected function legacyUsernameOrNull(mixed $value): ?string
    {
        return SptrackRecordDatetime::extractUsername(is_string($value) || is_numeric($value) ? (string) $value : null);
    }
}
