<?php

namespace App\Services;

use App\Models\Resolution;

class ResolutionPdfLinkService
{
    public function __construct(
        protected PdfAttachmentService $pdf,
    ) {}

    /**
     * Set pdf_path to resolutions/{series}/{resolution_no}.pdf without moving files.
     *
     * @return array{updated: int, skipped: int}
     */
    public function link(
        bool $onlyMissing = true,
        bool $dryRun = false,
        int $chunk = 500,
    ): array {
        $chunk = max(50, $chunk);
        $updated = 0;
        $skipped = 0;

        Resolution::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($resolutions) use ($onlyMissing, $dryRun, &$updated, &$skipped) {
                foreach ($resolutions as $resolution) {
                    $relative = $this->pdf->storageRelativePath(
                        (int) $resolution->series,
                        (string) $resolution->resolution_no,
                    );

                    if ($onlyMissing && filled($resolution->pdf_path)) {
                        $skipped++;

                        continue;
                    }

                    if ($resolution->pdf_path === $relative) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        $resolution->update(['pdf_path' => $relative]);
                    }

                    $updated++;
                }
            });

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
