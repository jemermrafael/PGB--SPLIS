<?php

namespace App\Console\Commands;

use App\Services\OrdinanceXlsxImporter;
use Illuminate\Console\Command;

class ImportOrdinancesFromXlsx extends Command
{
    protected $signature = 'splis:import-ordinances
                            {--path= : Path to Ordinance.xlsx}
                            {--sheet= : Worksheet name}
                            {--year= : Series year for imported ordinances}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import provincial ordinances from oldsp/Ordinance.xlsx';

    public function handle(OrdinanceXlsxImporter $importer): int
    {
        $path = $this->option('path') ?: config('ordinances.xlsx_path');
        $sheet = $this->option('sheet') ?: config('ordinances.xlsx_sheet');

        if (! is_file($path)) {
            $this->error("Workbook not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $seriesYear = $this->option('year') !== null
            ? (int) $this->option('year')
            : (int) config('ordinances.default_series_year', (int) now()->format('Y'));

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be made.');
        }

        $this->line("Series year: {$seriesYear}");

        $stats = $importer->import($path, $sheet, $dryRun, $seriesYear);

        $this->table(
            ['Created', 'Updated', 'Skipped'],
            [[$stats['created'], $stats['updated'], $stats['skipped']]],
        );

        if (! $dryRun) {
            $this->info('Ordinance import complete.');
        }

        return self::SUCCESS;
    }
}
