<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Category2;
use App\Models\Category3;
use App\Models\Category4;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Models\SeriesYear;
use App\Services\CsvExportReader;
use App\Support\DocumentType;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportFromCsv extends Command
{
    protected $signature = 'splis:import-from-csv
                            {--path= : Directory containing exported CSV files}
                            {--lookups : Import lookup tables from CSV}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import or enrich resolutions and lookups from SP Reso CSV exports';

    protected CsvExportReader $csv;

    protected array $categoryCache = [];

    protected array $category2Cache = [];

    protected array $category3Cache = [];

    protected array $category4Cache = [];

    protected array $departmentCache = [];

    protected array $municipalityCache = [];

    public function handle(CsvExportReader $csv): int
    {
        $this->csv = $csv;
        $directory = $csv->resolveDirectory($this->option('path'));

        if (! is_dir($directory)) {
            $this->error("CSV directory not found: {$directory}");

            return self::FAILURE;
        }

        $this->info("Using CSV directory: {$directory}");

        if ($this->option('lookups')) {
            $this->importLookups($directory);
        }

        $spFile = $csv->findNewest($directory, 'SP_');
        if (! $spFile) {
            $this->error('No SP_*.csv file found.');

            return self::FAILURE;
        }

        $this->importResolutions($spFile);

        return self::SUCCESS;
    }

    protected function importLookups(string $directory): void
    {
        $this->info('Importing lookups from CSV...');

        $this->importSimpleLookup($directory, '_zCategory1__', Category::class, 'legacy_id', fn ($row) => [
            'legacy_id' => (int) $row['ID'],
            'description' => $row['Desc'] ?? 'Unknown',
        ]);

        foreach ($this->csv->rows($this->requireFile($directory, '_zCategory2__')) as $row) {
            $parent = Category::where('legacy_id', (int) $row['Cat1_ID'])->first();
            if ($parent) {
                Category2::updateOrCreate(
                    ['legacy_id' => (int) $row['ID']],
                    ['category_id' => $parent->id, 'description' => $row['Desc'] ?? 'Unknown']
                );
            }
        }

        foreach ($this->csv->rows($this->requireFile($directory, '_zCategory3__')) as $row) {
            $parent = Category2::where('legacy_id', (int) $row['Cat2_ID'])->first();
            if ($parent) {
                Category3::updateOrCreate(
                    ['legacy_id' => (int) $row['ID']],
                    ['category2_id' => $parent->id, 'description' => $row['Desc'] ?? 'Unknown']
                );
            }
        }

        foreach ($this->csv->rows($this->requireFile($directory, '_zCategory4__')) as $row) {
            $parent = Category3::where('legacy_id', (int) $row['Cat3_ID'])->first();
            if ($parent) {
                Category4::updateOrCreate(
                    ['legacy_id' => (int) $row['ID']],
                    ['category3_id' => $parent->id, 'description' => $row['Desc'] ?? 'Unknown']
                );
            }
        }

        foreach ($this->csv->rows($this->requireFile($directory, '_zDepartment__')) as $row) {
            Department::updateOrCreate(
                ['code' => (int) $row['Code']],
                [
                    'description' => $row['Desc'] ?? '',
                    'abbreviation' => $row['Abb'] ?? null,
                ]
            );
        }

        foreach ($this->csv->rows($this->requireFile($directory, '_zMunicipality__')) as $row) {
            Municipality::updateOrCreate(
                ['code' => (int) $row['Code']],
                [
                    'description' => $row['Desc'] ?? '',
                    'zipcode' => $row['zipcode'] ?? null,
                    'district' => $row['district'] !== null ? (int) $row['district'] : null,
                ]
            );
        }

        $seriesFile = $this->csv->findNewest($directory, '_zSeriesYr__');
        if ($seriesFile) {
            foreach ($this->csv->rows($seriesFile) as $row) {
                $year = (int) ($row['seriesyr'] ?? $row['SeriesYr'] ?? $row['Series'] ?? 0);
                if ($year > 0) {
                    SeriesYear::updateOrCreate(['year' => $year]);
                }
            }
        }

        $this->info('Lookup import from CSV complete.');
    }

    protected function importSimpleLookup(string $directory, string $prefix, string $model, string $key, callable $map): void
    {
        $file = $this->requireFile($directory, $prefix);
        foreach ($this->csv->rows($file) as $row) {
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

    protected function importResolutions(string $spFile): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->warmCaches();

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Importing resolutions from: '.basename($spFile));

        $imported = 0;
        $skipped = 0;

        $rows = $this->csv->rows($spFile);
        $bar = $this->output->createProgressBar();
        $bar->start();

        foreach ($rows as $row) {
            $bar->advance();

            $legacyId = (int) ($row['ID'] ?? 0);
            $resolutionNo = trim((string) ($row['Resolution_No'] ?? ''));
            $series = (int) ($row['Series'] ?? 0);

            if ($legacyId < 1 || $resolutionNo === '' || $series < 1) {
                $skipped++;

                continue;
            }

            $payload = [
                'resolution_no' => Str::limit($resolutionNo, 50, ''),
                'resolution_title' => $row['Resolution_Title'] ?? '',
                'document_type' => DocumentType::forMigratedRecord(),
                'series' => $series,
                'department_id' => $this->departmentId($row['Office'] ?? null),
                'date_approved' => $this->parseDate($row['Date_App_En'] ?? null, $series),
                'sponsored_by' => $row['Sponsored_By'] ? Str::limit($row['Sponsored_By'], 100, '') : null,
                'category_id' => $this->categoryId($row['Category'] ?? null),
                'category2_id' => $this->category2Id($row['Sub_Cat1'] ?? null),
                'category3_id' => $this->category3Id($row['Sub_Cat2'] ?? null),
                'category4_id' => $this->category4Id($row['Sub_Cat3'] ?? null),
                'keyword' => $row['Keyword'] ? Str::limit($row['Keyword'], 100, '') : null,
                'committee' => $row['Comittee'] ? Str::limit($row['Comittee'], 100, '') : null,
                'app_ord_no' => $row['App_Ord_No'] ? Str::limit((string) $row['App_Ord_No'], 20, '') : null,
                'amount' => $this->parseAmount($row['Amount'] ?? null),
                'municipality_id' => $this->municipalityId($row['Municipality'] ?? null),
                'province' => $this->parseBool($row['Province'] ?? null),
                'status' => 'approved',
                'created_by' => null,
            ];

            if (! $dryRun) {
                Resolution::updateOrCreate(
                    ['legacy_sp_id' => $legacyId],
                    $payload
                );
            }

            $imported++;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Updated/created: {$imported}, skipped: {$skipped}.");
        $this->comment('Full resolution titles are restored from the CSV export.');
    }

    protected function warmCaches(): void
    {
        $this->categoryCache = Category::pluck('id', 'legacy_id')->all();
        $this->category2Cache = Category2::pluck('id', 'legacy_id')->all();
        $this->category3Cache = Category3::pluck('id', 'legacy_id')->all();
        $this->category4Cache = Category4::pluck('id', 'legacy_id')->all();
        $this->departmentCache = Department::pluck('id', 'code')->all();
        $this->municipalityCache = Municipality::pluck('id', 'code')->all();
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

    protected function municipalityId(mixed $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        $code = (int) $code;

        return $code > 0 ? ($this->municipalityCache[$code] ?? null) : null;
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
