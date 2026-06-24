<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Category2;
use App\Models\Category3;
use App\Models\Category4;
use App\Models\Department;
use App\Models\Legacy\SpResolution;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Support\DocumentType;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportResolutions extends Command
{
    protected $signature = 'splis:import-resolutions
                            {--chunk=500 : Rows per batch}
                            {--from= : Start at legacy sp.ID}
                            {--limit= : Max rows to import}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import resolutions from spreso.sp into splis.resolutions';

    protected array $categoryCache = [];

    protected array $category2Cache = [];

    protected array $category3Cache = [];

    protected array $category4Cache = [];

    protected array $departmentCache = [];

    protected array $municipalityCache = [];

    public function handle(): int
    {
        if (Category::count() === 0) {
            $this->error('Run splis:import-lookups first.');

            return self::FAILURE;
        }

        $this->warmCaches();

        $chunk = max(100, (int) $this->option('chunk'));
        $from = $this->option('from') ? (int) $this->option('from') : null;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = SpResolution::query()->orderBy('ID');
        if ($from) {
            $query->where('ID', '>=', $from);
        }

        $total = $limit ? min($limit, (clone $query)->count()) : (clone $query)->count();
        $this->info(($dryRun ? '[DRY RUN] ' : '')."Importing {$total} resolutions from spreso...");

        $imported = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($chunk, function ($rows) use (&$imported, &$skipped, $dryRun, $limit, $bar) {
            foreach ($rows as $row) {
                if ($limit !== null && $imported + $skipped >= $limit) {
                    return false;
                }

                $resolutionNo = trim((string) ($row->Resolution_No ?? ''));
                $series = (int) ($row->Series ?? 0);

                if ($resolutionNo === '' || $series < 1) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $payload = [
                    'resolution_no' => Str::limit($resolutionNo, 50, ''),
                    'resolution_title' => $row->Resolution_Title ?? '',
                    'document_type' => DocumentType::forMigratedRecord(),
                    'series' => $series,
                    'department_id' => $this->departmentId($row->Office),
                    'date_approved' => $this->parseDate($row->Date_App_En, $series),
                    'sponsored_by' => $row->Sponsored_By ? Str::limit($row->Sponsored_By, 100, '') : null,
                    'category_id' => $this->categoryId($row->Category),
                    'category2_id' => $this->category2Id($row->Sub_Cat1),
                    'category3_id' => $this->category3Id($row->Sub_Cat2),
                    'category4_id' => $this->category4Id($row->Sub_Cat3),
                    'keyword' => $row->Keyword ? Str::limit($row->Keyword, 100, '') : null,
                    'committee' => $row->Comittee ? Str::limit($row->Comittee, 100, '') : null,
                    'app_ord_no' => $row->App_Ord_No ? Str::limit($row->App_Ord_No, 20, '') : null,
                    'amount' => $row->Amount ?: null,
                    'municipality_id' => $this->municipalityId($row->Municipality),
                    'province' => (bool) $row->Province,
                    'status' => 'approved',
                    'created_by' => null,
                ];

                if (! $dryRun) {
                    Resolution::updateOrCreate(
                        ['legacy_sp_id' => (int) $row->ID],
                        $payload
                    );
                }

                $imported++;
                $bar->advance();
            }
        }, 'ID');

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Imported: {$imported}, skipped: {$skipped}.");

        if (! $dryRun) {
            $this->comment('Legacy titles were stored as VARCHAR(50) in spreso — full titles may not exist in source data.');
        }

        return self::SUCCESS;
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

    protected function categoryId(?int $legacyId): ?int
    {
        return $legacyId ? ($this->categoryCache[$legacyId] ?? null) : null;
    }

    protected function category2Id(?int $legacyId): ?int
    {
        return $legacyId ? ($this->category2Cache[$legacyId] ?? null) : null;
    }

    protected function category3Id(?int $legacyId): ?int
    {
        return $legacyId ? ($this->category3Cache[$legacyId] ?? null) : null;
    }

    protected function category4Id(?int $legacyId): ?int
    {
        return $legacyId ? ($this->category4Cache[$legacyId] ?? null) : null;
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

        return $this->municipalityCache[(int) $code] ?? null;
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

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
