<?php

namespace App\Console\Commands;

use App\Services\AppropriationOrdinancePdfMirrorService;
use Illuminate\Console\Command;

class MirrorAppropriationOrdinancePdfs extends Command
{
    protected $signature = 'appropriation-ordinances:mirror-pdfs
                            {--limit=5 : Max records to process (0 = all)}
                            {--id= : Mirror a single appropriation ordinance by ID}
                            {--overwrite : Re-download even when pdf_path already exists}
                            {--dry-run : Count matching rows without downloading}';

    protected $description = 'Download Appropriation Ordinance pdf_url from Drive into private storage and set pdf_path';

    public function handle(AppropriationOrdinancePdfMirrorService $mirror): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $id = $this->option('id') !== null && $this->option('id') !== ''
            ? (int) $this->option('id')
            : null;
        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Mirroring Appropriation Ordinance PDFs from pdf_url…');

        $stats = $mirror->mirrorMany(
            onlyMissing: ! $overwrite,
            dryRun: $dryRun,
            limit: $id !== null ? 0 : $limit,
            recordId: $id,
        );

        $this->table(['Metric', 'Count'], [
            ['Mirrored', $stats['mirrored']],
            ['Skipped', $stats['skipped']],
            ['Failed', $stats['failed']],
        ]);

        foreach (array_slice($stats['errors'], 0, 20) as $error) {
            $this->warn($error);
        }

        return $stats['failed'] > 0 && $stats['mirrored'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
