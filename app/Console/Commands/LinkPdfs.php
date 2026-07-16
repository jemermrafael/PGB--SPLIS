<?php

namespace App\Console\Commands;

use App\Services\ResolutionPdfLinkService;
use Illuminate\Console\Command;

class LinkPdfs extends Command
{
    protected $signature = 'resolutions:link-pdfs
                            {--chunk=500 : Resolutions per batch}
                            {--dry-run : Preview without updating rows}
                            {--only-missing : Skip rows that already have pdf_path}';

    protected $description = 'Set pdf_path to resolutions/{series}/{resolution_no}.pdf on each resolution (does not copy files)';

    public function handle(ResolutionPdfLinkService $linker): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing');
        $chunk = max(50, (int) $this->option('chunk'));

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Backfilling resolution pdf_path values...');

        $stats = $linker->link(
            onlyMissing: $onlyMissing,
            dryRun: $dryRun,
            chunk: $chunk,
        );

        $this->table(['Metric', 'Count'], [
            ['Updated', $stats['updated']],
            ['Skipped', $stats['skipped']],
        ]);

        return self::SUCCESS;
    }
}
