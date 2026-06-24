<?php

namespace App\Console\Commands;

use App\Models\Resolution;
use App\Services\PdfAttachmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LinkPdfs extends Command
{
    protected $signature = 'resolutions:link-pdfs
                            {--chunk=500 : Resolutions per batch}
                            {--copy : Copy instead of move}
                            {--dry-run : Preview without moving files}
                            {--only-missing : Skip rows that already have pdf_path on disk}';

    protected $description = 'Move PDF files from oldsp/PDF into storage/app/resolutions and save pdf_path on each resolution';

    public function handle(PdfAttachmentService $pdf): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $copy = (bool) $this->option('copy');
        $onlyMissing = (bool) $this->option('only-missing');
        $chunk = max(50, (int) $this->option('chunk'));

        $total = Resolution::query()->count();
        $this->info(($dryRun ? '[DRY RUN] ' : '')."Processing {$total} resolutions...");
        $this->line('Legacy PDF root: '.$pdf->legacyRoot());

        $linked = 0;
        $skipped = 0;
        $missing = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Resolution::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($resolutions) use ($pdf, $dryRun, $copy, $onlyMissing, &$linked, &$skipped, &$missing, $bar) {
                foreach ($resolutions as $resolution) {
                    $relative = $pdf->storageRelativePath($resolution->series, $resolution->resolution_no);
                    $destination = storage_path('app/'.$relative);

                    if ($onlyMissing && $resolution->pdf_path && $pdf->absolutePath($resolution->pdf_path)) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }

                    if (File::isFile($destination)) {
                        if (! $dryRun && $resolution->pdf_path !== $relative) {
                            $resolution->update(['pdf_path' => $relative]);
                        }
                        $linked++;
                        $bar->advance();

                        continue;
                    }

                    $source = $pdf->findLegacyFile($resolution->series, $resolution->resolution_no);
                    if ($source === null) {
                        $missing++;
                        $bar->advance();

                        continue;
                    }

                    if (! $dryRun) {
                        File::ensureDirectoryExists(dirname($destination));

                        if ($copy) {
                            File::copy($source, $destination);
                        } else {
                            File::move($source, $destination);
                        }

                        $resolution->update(['pdf_path' => $relative]);
                    }

                    $linked++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Count'], [
            ['Linked', $linked],
            ['Skipped (already set)', $skipped],
            ['No PDF on disk', $missing],
        ]);

        return self::SUCCESS;
    }
}
