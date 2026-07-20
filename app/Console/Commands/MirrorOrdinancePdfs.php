<?php

namespace App\Console\Commands;

use App\Services\OrdinancePdfMirrorService;
use Illuminate\Console\Command;

class MirrorOrdinancePdfs extends Command
{
    protected $signature = 'ordinances:mirror-pdfs
                            {--limit=5 : Max ordinances to process (0 = all)}
                            {--id= : Mirror a single ordinance by ID}
                            {--overwrite : Re-download even when pdf_path already exists}
                            {--dry-run : Count matching rows without downloading}';

    protected $description = 'Download ordinance pdf_url (Google Drive / direct) into local storage and set pdf_path';

    public function handle(OrdinancePdfMirrorService $mirror): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $id = $this->option('id') !== null && $this->option('id') !== ''
            ? (int) $this->option('id')
            : null;
        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Mirroring ordinance PDFs from pdf_url…');

        $stats = $mirror->mirrorMany(
            onlyMissing: ! $overwrite,
            dryRun: $dryRun,
            limit: $id !== null ? 0 : $limit,
            ordinanceId: $id,
        );

        $this->table(['Metric', 'Count'], [
            ['Mirrored', $stats['mirrored']],
            ['Skipped', $stats['skipped']],
            ['Failed', $stats['failed']],
        ]);

        foreach (array_slice($stats['errors'], 0, 20) as $error) {
            $this->warn($error);
        }

        if (count($stats['errors']) > 20) {
            $this->warn('… and '.(count($stats['errors']) - 20).' more errors.');
        }

        return $stats['failed'] > 0 && $stats['mirrored'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
