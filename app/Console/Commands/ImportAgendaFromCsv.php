<?php

namespace App\Console\Commands;

use App\Services\AgendaCsvImporter;
use Illuminate\Console\Command;

class ImportAgendaFromCsv extends Command
{
    protected $signature = 'splis:import-agenda-from-csv
                            {--csv= : Agenda data CSV path}
                            {--links= : Optional CSV with Google Drive PDF URLs (e.g. oldsp/Agenda3.csv)}';

    protected $description = 'Import agenda tracker rows from CSV';

    public function handle(AgendaCsvImporter $importer): int
    {
        $csv = $this->option('csv');
        $links = $this->option('links');

        try {
            $stats = $importer->import(
                $csv ?: null,
                $links ?: null,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Total rows', 'Imported', 'Updated', 'Urgent (no tracking no.)'],
            [[$stats['total'], $stats['imported'], $stats['updated'], $stats['urgent'] ?? 0]]
        );

        if (! is_file(config('agenda.csv_links_path'))) {
            $this->comment('Tip: agenda7.csv embeds PDF links in column B; a separate links file is optional.');
        }

        return self::SUCCESS;
    }
}
