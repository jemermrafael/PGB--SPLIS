<?php

namespace App\Console\Commands;

use App\Models\ReferenceMaterialVersion;
use App\Services\PdfTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ReindexReferencePdfText extends Command
{
    protected $signature = 'references:reindex-pdfs
                            {--reference= : Reference material ID to limit reindexing}
                            {--force : Reindex even when extracted_text is already populated}';

    protected $description = 'Backfill or refresh extracted PDF text for reference material versions.';

    public function handle(PdfTextExtractor $extractor): int
    {
        $referenceId = $this->option('reference');
        $force = (bool) $this->option('force');

        $query = ReferenceMaterialVersion::query()
            ->where(function (Builder $builder): void {
                $builder->where('mime_type', 'like', '%pdf%')
                    ->orWhere('original_filename', 'like', '%.pdf');
            });

        if ($referenceId !== null && $referenceId !== '') {
            $query->where('reference_material_id', (int) $referenceId);
        }

        if (! $force) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('extracted_text')->orWhere('extracted_text', '');
            });
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('No PDF versions found for reindexing.');

            return self::SUCCESS;
        }

        $this->info("Reindexing {$count} PDF version(s)...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $indexed = 0;
        $missing = 0;

        $query->orderBy('id')->chunkById(100, function ($versions) use ($extractor, &$indexed, &$missing, $bar): void {
            foreach ($versions as $version) {
                $path = (string) $version->file_path;
                if ($path === '' || ! Storage::disk('local')->exists($path)) {
                    $missing++;
                    $bar->advance();
                    continue;
                }

                $absolutePath = Storage::disk('local')->path($path);
                $text = $extractor->extractFromPath($absolutePath);
                $version->update(['extracted_text' => $text]);
                $indexed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Indexed: {$indexed}. Missing files: {$missing}.");

        return self::SUCCESS;
    }
}

