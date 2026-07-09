<?php

namespace App\Console\Commands;

use App\Services\AppropriationOrdinanceCsvImporter;
use Illuminate\Console\Command;

class ImportAppropriationOrdinancesFromCsv extends Command
{
    protected $signature = 'splis:import-appropriation-ordinances
                            {--path= : Path to ApproOrd.csv}
                            {--year= : Series year for imported records}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import appropriation ordinances from oldsp/ApproOrd.csv';

    public function handle(AppropriationOrdinanceCsvImporter $importer): int
    {
        $path = $this->option('path') ?: config('appropriation_ordinances.csv_path');

        if (! is_file($path)) {
            $this->error("CSV not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $seriesYear = $this->option('year') !== null
            ? (int) $this->option('year')
            : null;

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be made.');
        }

        if ($seriesYear !== null) {
            $this->line("Series year override: {$seriesYear}");
        }

        try {
            $stats = $importer->import($path, $dryRun, $seriesYear);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Processed', 'Created', 'Updated', 'Skipped'],
            [[$stats['processed'], $stats['created'], $stats['updated'], $stats['skipped']]],
        );

        if (! $dryRun) {
            $this->info('Appropriation ordinance import complete.');
        }

        return self::SUCCESS;
    }
}
