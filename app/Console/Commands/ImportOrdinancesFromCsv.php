<?php

namespace App\Console\Commands;

use App\Services\OrdinanceCsvImporter;
use Illuminate\Console\Command;

class ImportOrdinancesFromCsv extends Command
{
    protected $signature = 'splis:import-ordinances-csv
                            {--path= : Path to Ordinances-*.csv}
                            {--year= : Series year for imported ordinances}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import provincial ordinances from oldsp/Ordinances-*.csv';

    public function handle(OrdinanceCsvImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $seriesYear = $this->option('year') !== null
            ? (int) $this->option('year')
            : null;

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be made.');
        }

        try {
            $stats = $importer->sync(
                dryRun: $dryRun,
                csvFilePath: $this->option('path'),
                seriesYear: $seriesYear,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('File: '.basename((string) $stats['csv_file']));

        if ($seriesYear !== null) {
            $this->line("Series year override: {$seriesYear}");
        }

        $this->table(
            ['Processed', 'Created', 'Updated', 'Skipped'],
            [[$stats['processed'], $stats['created'], $stats['updated'], $stats['skipped']]],
        );

        if (! $dryRun) {
            $this->info('Ordinance CSV import complete.');
        }

        return self::SUCCESS;
    }
}
