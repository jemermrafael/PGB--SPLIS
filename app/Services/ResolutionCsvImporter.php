<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Category2;
use App\Models\Category3;
use App\Models\Category4;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Models\SeriesYear;
use App\Support\DocumentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResolutionCsvImporter
{
    protected CsvExportReader $csv;

    /** @var array<int, int> */
    protected array $categoryCache = [];

    /** @var array<int, int> */
    protected array $category2Cache = [];

    /** @var array<int, int> */
    protected array $category3Cache = [];

    /** @var array<int, int> */
    protected array $category4Cache = [];

    /** @var array<int, int> */
    protected array $departmentCache = [];

    /** @var array<int, int> */
    protected array $municipalityCache = [];

    /** @var array<string, int> */
    protected array $municipalityNameCache = [];

    public function __construct(
        CsvExportReader $csv,
        protected ResolutionVersionService $versions,
    ) {
        $this->csv = $csv;
    }

    /**
     * @return array{
     *     directory: string,
     *     sp_file: ?string,
     *     processed: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     lookups_imported: bool
     * }
     */
    public function sync(
        ?string $directory = null,
        bool $includeLookups = false,
        bool $dryRun = false,
        ?string $spFilePath = null,
        ?int $userId = null,
    ): array {
        if ($spFilePath !== null) {
            if (! is_file($spFilePath)) {
                throw new \RuntimeException("Uploaded CSV not found: {$spFilePath}");
            }

            $spFile = $spFilePath;
            $directory = $this->csv->resolveDirectory($directory);
        } else {
            $directory = $this->csv->resolveDirectory($directory);

            if (! is_dir($directory)) {
                throw new \RuntimeException("CSV directory not found: {$directory}");
            }

            $spFile = $this->csv->findNewest($directory, 'SP_');

            if (! $spFile) {
                throw new \RuntimeException('No SP_*.csv file found.');
            }
        }

        if ($includeLookups) {
            if (! is_dir($directory)) {
                throw new \RuntimeException("Lookup CSV directory not found: {$directory}");
            }

            $this->importLookups($directory, $dryRun);
        }

        $this->warmCaches();

        $stats = [
            'directory' => $directory,
            'sp_file' => $spFile,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'csv_duplicate_legacy' => 0,
            'csv_duplicate_number_series' => 0,
            'conflicting_active_number' => 0,
            'duplicate_legacy_ids' => [],
            'duplicate_number_series_pairs' => [],
            'conflicting_active_number_details' => [],
            'lookups_imported' => $includeLookups,
        ];

        $seenLegacy = [];
        $seenNumberSeries = [];

        foreach ($this->csv->rows($spFile) as $row) {
            $legacyId = (int) ($row['ID'] ?? 0);
            $resolutionNo = trim((string) ($row['Resolution_No'] ?? ''));
            $series = (int) ($row['Series'] ?? 0);

            if ($legacyId < 1 || $resolutionNo === '' || $series < 1) {
                $stats['skipped']++;

                continue;
            }

            if (isset($seenLegacy[$legacyId])) {
                $stats['csv_duplicate_legacy']++;
                $stats['duplicate_legacy_ids'][$legacyId] = (string) $legacyId;
            }
            $seenLegacy[$legacyId] = true;

            $numberKey = $series.'|'.$resolutionNo;
            $numberLabel = $series.'/'.$resolutionNo;
            if (isset($seenNumberSeries[$numberKey])) {
                $stats['csv_duplicate_number_series']++;
                $stats['duplicate_number_series_pairs'][$numberKey] = $numberLabel;
            }
            $seenNumberSeries[$numberKey] = true;

            $conflict = Resolution::query()
                ->where('series', $series)
                ->where('resolution_no', $resolutionNo)
                ->where(function ($query) use ($legacyId) {
                    $query->whereNull('legacy_sp_id')->orWhere('legacy_sp_id', '!=', $legacyId);
                })
                ->first(['id', 'legacy_sp_id', 'series', 'resolution_no']);

            if ($conflict !== null) {
                $stats['conflicting_active_number']++;
                $conflictKey = $numberKey.'|'.$legacyId;
                $legacyLabel = $conflict->legacy_sp_id
                    ? 'legacy ID '.$conflict->legacy_sp_id
                    : 'no legacy ID';
                $stats['conflicting_active_number_details'][$conflictKey] = sprintf(
                    '%s (CSV ID %d vs resolution #%d, %s)',
                    $numberLabel,
                    $legacyId,
                    $conflict->id,
                    $legacyLabel,
                );
            }

            $payload = $this->buildPayload($row, $series);
            $exists = Resolution::query()->where('legacy_sp_id', $legacyId)->exists();

            if ($dryRun) {
                $stats[$exists ? 'updated' : 'created']++;
            } else {
                $existing = Resolution::query()->where('legacy_sp_id', $legacyId)->first();

                if ($existing !== null) {
                    DB::transaction(function () use ($existing, $payload, $userId): void {
                        $existing->update($payload);
                        $existing->refresh();
                        $this->versions->resetToImportedVersion($existing, $userId);
                    });

                    $stats['updated']++;
                } else {
                    DB::transaction(function () use ($payload, $legacyId, $userId): void {
                        $resolution = Resolution::query()->create(array_merge($payload, [
                            'legacy_sp_id' => $legacyId,
                        ]));
                        $this->versions->resetToImportedVersion($resolution, $userId);
                    });
                    $stats['created']++;
                }
            }

            $stats['processed']++;
        }

        $stats['duplicate_legacy_ids'] = array_values($stats['duplicate_legacy_ids']);
        $stats['duplicate_number_series_pairs'] = array_values($stats['duplicate_number_series_pairs']);
        $stats['conflicting_active_number_details'] = array_values($stats['conflicting_active_number_details']);

        return $stats;
    }

    protected function importLookups(string $directory, bool $dryRun): void
    {
        $this->importSimpleLookup($directory, '_zCategory1__', Category::class, 'legacy_id', fn ($row) => [
            'legacy_id' => (int) $row['ID'],
            'description' => $row['Desc'] ?? 'Unknown',
        ], $dryRun);

        foreach ($this->csv->rows($this->requireFile($directory, '_zCategory2__')) as $row) {
            $parent = Category::where('legacy_id', (int) $row['Cat1_ID'])->first();
            if ($parent && ! $dryRun) {
                Category2::updateOrCreate(
                    ['legacy_id' => (int) $row['ID']],
                    ['category_id' => $parent->id, 'description' => $row['Desc'] ?? 'Unknown']
                );
            }
        }

        foreach ($this->csv->rows($this->requireFile($directory, '_zCategory3__')) as $row) {
            $parent = Category2::where('legacy_id', (int) $row['Cat2_ID'])->first();
            if ($parent && ! $dryRun) {
                Category3::updateOrCreate(
                    ['legacy_id' => (int) $row['ID']],
                    ['category2_id' => $parent->id, 'description' => $row['Desc'] ?? 'Unknown']
                );
            }
        }

        foreach ($this->csv->rows($this->requireFile($directory, '_zCategory4__')) as $row) {
            $parent = Category3::where('legacy_id', (int) $row['Cat3_ID'])->first();
            if ($parent && ! $dryRun) {
                Category4::updateOrCreate(
                    ['legacy_id' => (int) $row['ID']],
                    ['category3_id' => $parent->id, 'description' => $row['Desc'] ?? 'Unknown']
                );
            }
        }

        foreach ($this->csv->rows($this->requireFile($directory, '_zDepartment__')) as $row) {
            if (! $dryRun) {
                Department::updateOrCreate(
                    ['code' => (int) $row['Code']],
                    [
                        'description' => $row['Desc'] ?? '',
                        'abbreviation' => $row['Abb'] ?? null,
                    ]
                );
            }
        }

        foreach ($this->csv->rows($this->requireFile($directory, '_zMunicipality__')) as $row) {
            if (! $dryRun) {
                Municipality::updateOrCreate(
                    ['code' => (int) $row['Code']],
                    [
                        'description' => $row['Desc'] ?? '',
                        'zipcode' => $row['zipcode'] ?? null,
                        'district' => $row['district'] !== null ? (int) $row['district'] : null,
                    ]
                );
            }
        }

        $seriesFile = $this->csv->findNewest($directory, '_zSeriesYr__');
        if ($seriesFile) {
            foreach ($this->csv->rows($seriesFile) as $row) {
                $year = (int) ($row['seriesyr'] ?? $row['SeriesYr'] ?? $row['Series'] ?? 0);
                if ($year > 0 && ! $dryRun) {
                    SeriesYear::updateOrCreate(['year' => $year]);
                }
            }
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    protected function importSimpleLookup(string $directory, string $prefix, string $model, string $key, callable $map, bool $dryRun): void
    {
        $file = $this->requireFile($directory, $prefix);
        foreach ($this->csv->rows($file) as $row) {
            if ($dryRun) {
                continue;
            }

            $data = $map($row);
            $model::updateOrCreate([$key => $data[$key]], $data);
        }
    }

    protected function requireFile(string $directory, string $prefix): string
    {
        $file = $this->csv->findNewest($directory, $prefix);
        if (! $file) {
            throw new \RuntimeException("CSV file not found for prefix: {$prefix}");
        }

        return $file;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    protected function buildPayload(array $row, int $series): array
    {
        return [
            'resolution_no' => Str::limit(trim((string) ($row['Resolution_No'] ?? '')), 50, ''),
            'resolution_title' => $row['Resolution_Title'] ?? '',
            'document_type' => DocumentType::forMigratedRecord(),
            'series' => $series,
            'department_id' => $this->departmentId($row['Office'] ?? null),
            'date_approved' => $this->parseDate($row['Date_App_En'] ?? null, $series),
            'sponsored_by' => ($row['Sponsored_By'] ?? null)
                ? Str::limit((string) $row['Sponsored_By'], 100, '')
                : null,
            'category_id' => $this->categoryId($row['Category'] ?? null),
            'category2_id' => $this->category2Id($row['Sub_Cat1'] ?? null),
            'category3_id' => $this->category3Id($row['Sub_Cat2'] ?? null),
            'category4_id' => $this->category4Id($row['Sub_Cat3'] ?? null),
            'keyword' => ($row['Keyword'] ?? null)
                ? Str::limit((string) $row['Keyword'], 100, '')
                : null,
            'committee' => ($row['Comittee'] ?? null)
                ? Str::limit((string) $row['Comittee'], 100, '')
                : null,
            'app_ord_no' => ($row['App_Ord_No'] ?? null)
                ? Str::limit((string) $row['App_Ord_No'], 20, '')
                : null,
            'amount' => $this->parseAmount($row['Amount'] ?? null),
            'municipality_id' => $this->municipalityId(
                $row['MunCode'] ?? $row['Municipality'] ?? null,
                $row['Municipality'] ?? null,
            ),
            'province' => $this->parseBool($row['Province'] ?? null),
            'status' => 'approved',
            'created_by' => null,
        ];
    }

    protected function warmCaches(): void
    {
        $this->categoryCache = Category::pluck('id', 'legacy_id')->all();
        $this->category2Cache = Category2::pluck('id', 'legacy_id')->all();
        $this->category3Cache = Category3::pluck('id', 'legacy_id')->all();
        $this->category4Cache = Category4::pluck('id', 'legacy_id')->all();
        $this->departmentCache = Department::pluck('id', 'code')->all();
        $this->municipalityCache = Municipality::pluck('id', 'code')->all();
        $this->municipalityNameCache = Municipality::query()
            ->get(['id', 'description'])
            ->mapWithKeys(fn (Municipality $municipality) => [
                $this->normalizeMunicipalityName($municipality->description) => (int) $municipality->id,
            ])
            ->all();
    }

    protected function categoryId(?string $legacyId): ?int
    {
        return $legacyId ? ($this->categoryCache[(int) $legacyId] ?? null) : null;
    }

    protected function category2Id(?string $legacyId): ?int
    {
        return $legacyId ? ($this->category2Cache[(int) $legacyId] ?? null) : null;
    }

    protected function category3Id(?string $legacyId): ?int
    {
        return $legacyId ? ($this->category3Cache[(int) $legacyId] ?? null) : null;
    }

    protected function category4Id(?string $legacyId): ?int
    {
        return $legacyId ? ($this->category4Cache[(int) $legacyId] ?? null) : null;
    }

    protected function departmentId(mixed $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        return $this->departmentCache[(int) $code] ?? null;
    }

    protected function municipalityId(mixed $code, mixed $name = null): ?int
    {
        if ($code !== null && trim((string) $code) !== '') {
            $numericCode = (int) $code;

            if ($numericCode > 0 && isset($this->municipalityCache[$numericCode])) {
                return $this->municipalityCache[$numericCode];
            }
        }

        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return $this->municipalityNameCache[$this->normalizeMunicipalityName($name)] ?? null;
    }

    protected function normalizeMunicipalityName(string $name): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($name)) ?? trim($name), 'UTF-8');
    }

    protected function parseAmount(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $amount = (int) round((float) $value);

        return $amount > 0 ? $amount : null;
    }

    protected function parseBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes'], true);
    }

    protected function parseDate(?string $value, int $series): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            try {
                return Carbon::createFromDate($series, (int) $m[1], (int) $m[2])->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
