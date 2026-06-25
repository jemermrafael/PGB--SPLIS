<?php

namespace App\Console\Commands;

use App\Services\SptrackAnalyzer;
use App\Services\SptrackReader;
use Illuminate\Console\Command;

class AnalyzeSptrack extends Command
{
    protected $signature = 'splis:analyze-sptrack
                            {--csv= : CSV path (use with --source=csv)}
                            {--source= : Force database or csv}
                            {--keep-queue : Do not clear pending/approved queue rows before analyzing}
                            {--limit= : Max sptrack rows to analyze}';

    protected $description = 'Analyze sptrack Files records and populate the migration review queue';

    public function handle(SptrackAnalyzer $analyzer, SptrackReader $reader): int
    {
        $source = $this->resolveSource($reader);
        $label = $source === 'csv' ? 'CSV export' : 'MySQL sptrack.Files';
        $this->info("Analyzing sptrack from {$label}...");

        try {
            $stats = $analyzer->analyze(
                csvPath: $this->option('csv'),
                fresh: ! $this->option('keep-queue'),
                source: $source,
                limit: $this->option('limit') ? (int) $this->option('limit') : null,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Analysis complete.');
        $this->table(
            ['Batch', 'Total', 'High confidence', 'Needs review', 'Create new', 'Skip'],
            [[
                $stats['batch_id'],
                $stats['total'],
                $stats['high'],
                $stats['review'],
                $stats['create'],
                $stats['skip'],
            ]]
        );
        $this->comment('Open Admin → SP Migration to review and approve rows before applying.');

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

        return $reader->canUseDatabase() ? 'database' : 'csv';
    }
}
