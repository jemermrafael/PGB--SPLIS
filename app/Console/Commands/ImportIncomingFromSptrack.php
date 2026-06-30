<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\IncomingDocumentImporter;
use App\Services\SptrackReader;
use Illuminate\Console\Command;

class ImportIncomingFromSptrack extends Command
{
    protected $signature = 'splis:import-incoming-from-sptrack
                            {--csv= : CSV path (use with --source=csv)}
                            {--source= : Force database or csv}';

    protected $description = 'Import sptrack Files into incoming documents without linking to resolutions';

    public function handle(IncomingDocumentImporter $importer, SptrackReader $reader): int
    {
        $source = $this->resolveSource($reader);
        $label = $source === 'csv' ? 'CSV export' : 'MySQL sptrack.Files';
        $this->info("Importing incoming documents from {$label}...");

        try {
            $stats = $importer->importFromSptrack($this->option('csv'), $source);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Total rows', 'Imported', 'Skipped (existing)'],
            [[$stats['total'], $stats['imported'], $stats['skipped']]]
        );

        if ($stats['imported'] > 0 || $stats['skipped'] > 0) {
            ActivityLog::log('incoming.imported_from_sptrack', null, [
                'source' => $source,
                'total' => $stats['total'],
                'imported' => $stats['imported'],
                'skipped' => $stats['skipped'],
            ], null);
        }

        $this->comment('Link incoming items to resolutions manually under Incoming → detail page.');

        return self::SUCCESS;
    }

    protected function resolveSource(SptrackReader $reader): string
    {
        $forced = $this->option('source');
        if (in_array($forced, ['database', 'csv'], true)) {
            return $forced;
        }

        if ($this->option('csv')) {
            return 'csv';
        }

        if ($reader->defaultCsvPathExists()) {
            return 'csv';
        }

        return $reader->canUseDatabase() ? 'database' : 'csv';
    }
}
