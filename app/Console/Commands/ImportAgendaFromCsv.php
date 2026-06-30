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
            ['Total rows', 'Imported', 'Updated'],
            [[$stats['total'], $stats['imported'], $stats['updated']]]
        );

        if (! is_file(config('agenda.csv_links_path'))) {
            $this->comment('Tip: add PDF links via oldsp/Agenda3.csv and re-run with --links=oldsp/Agenda3.csv');
        }

        return self::SUCCESS;
    }
}
