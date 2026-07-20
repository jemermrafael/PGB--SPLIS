<?php

namespace App\Services;

use App\Models\Ordinance;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrdinancePdfMirrorService
{
    public function __construct(
        protected GoogleDrivePdfDownloader $downloader,
        protected OrdinancePdfService $pdfs,
    ) {}

    /**
     * Download pdf_url into local storage and set pdf_path.
     *
     * @return array{ok: bool, message: string, path?: string}
     */
    public function mirror(Ordinance $ordinance, bool $overwrite = false): array
    {
        if (! $overwrite && $this->pdfs->existsFor($ordinance)) {
            return [
                'ok' => true,
                'message' => 'Local PDF already present; skipped.',
                'path' => $ordinance->pdf_path,
            ];
        }

        $url = trim((string) ($ordinance->pdf_url ?? ''));

        if ($url === '') {
            return [
                'ok' => false,
                'message' => 'No pdf_url to download.',
            ];
        }

        try {
            $bytes = $this->downloader->download($url);
            $path = $this->pdfs->storeBytes(
                $bytes,
                (int) $ordinance->series_year,
                (int) $ordinance->ordinance_no,
            );
            $ordinance->update(['pdf_path' => $path]);

            return [
                'ok' => true,
                'message' => 'Mirrored to '.$path,
                'path' => $path,
            ];
        } catch (Throwable $e) {
            Log::warning('Ordinance PDF mirror failed', [
                'ordinance_id' => $ordinance->id,
                'pdf_url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{mirrored: int, skipped: int, failed: int, errors: list<string>}
     */
    public function mirrorMany(
        bool $onlyMissing = true,
        bool $dryRun = false,
        int $limit = 0,
        ?int $ordinanceId = null,
    ): array {
        $query = Ordinance::query()
            ->whereNotNull('pdf_url')
            ->where('pdf_url', '!=', '');

        if ($ordinanceId !== null) {
            $query->whereKey($ordinanceId);
        }

        if ($onlyMissing) {
            $query->where(function ($q): void {
                $q->whereNull('pdf_path')->orWhere('pdf_path', '');
            });
        }

        $query->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $mirrored = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($query->cursor() as $ordinance) {
            /** @var Ordinance $ordinance */
            if ($dryRun) {
                $skipped++;

                continue;
            }

            $result = $this->mirror($ordinance, overwrite: ! $onlyMissing);

            if ($result['ok'] && str_contains($result['message'], 'skipped')) {
                $skipped++;
            } elseif ($result['ok']) {
                $mirrored++;
            } else {
                $failed++;
                $errors[] = 'Ord #'.$ordinance->id.': '.$result['message'];
            }
        }

        return compact('mirrored', 'skipped', 'failed', 'errors');
    }
}
