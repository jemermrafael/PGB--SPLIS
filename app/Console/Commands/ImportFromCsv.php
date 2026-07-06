<?php

namespace App\Console\Commands;

use App\Services\CsvExportReader;
use App\Services\ResolutionCsvImporter;
use Illuminate\Console\Command;

class ImportFromCsv extends Command
{
    protected $signature = 'splis:import-from-csv
                            {--path= : Directory containing exported CSV files}
                            {--lookups : Import lookup tables from CSV}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import or enrich resolutions and lookups from SP Reso CSV exports';

    public function handle(ResolutionCsvImporter $importer, CsvExportReader $csv): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $stats = $importer->sync(
                directory: $this->option('path'),
                includeLookups: (bool) $this->option('lookups'),
                dryRun: $dryRun,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $directory = $csv->resolveDirectory($this->option('path'));
        $this->info("Using CSV directory: {$directory}");

        if ($this->option('lookups')) {
            $this->info($dryRun ? '[DRY RUN] Lookups would be imported.' : 'Lookup import from CSV complete.');
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Importing resolutions from: '.basename((string) $stats['sp_file']));
        $this->table(
            ['Processed', 'Created', 'Updated', 'Skipped'],
            [[$stats['processed'], $stats['created'], $stats['updated'], $stats['skipped']]],
        );
        $this->comment('Full resolution titles are restored from the CSV export.');

        return self::SUCCESS;
    }
}
