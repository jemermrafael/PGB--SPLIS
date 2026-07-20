<?php

namespace App\Services;

use App\Models\AppropriationOrdinance;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppropriationOrdinancePdfMirrorService
{
    public function __construct(
        protected GoogleDrivePdfDownloader $downloader,
        protected AppropriationOrdinancePdfService $pdfs,
    ) {}

    /**
     * @return array{ok: bool, message: string, path?: string}
     */
    public function mirror(AppropriationOrdinance $record, bool $overwrite = false): array
    {
        if (! $overwrite && $this->pdfs->existsFor($record)) {
            return [
                'ok' => true,
                'message' => 'Local file already present; skipped.',
                'path' => $record->pdf_path,
            ];
        }

        $url = trim((string) ($record->pdf_url ?? ''));

        if ($url === '') {
            return [
                'ok' => false,
                'message' => 'No pdf_url to download.',
            ];
        }

        try {
            $file = $this->downloader->downloadFile($url);
            $path = $this->pdfs->storeBytes(
                $file['contents'],
                (int) $record->series_year,
                (int) $record->ordinance_no,
                $file['extension'],
            );
            $record->update(['pdf_path' => $path]);

            return [
                'ok' => true,
                'message' => 'Mirrored to '.$path,
                'path' => $path,
            ];
        } catch (Throwable $e) {
            Log::warning('Appropriation Ordinance PDF mirror failed', [
                'appropriation_ordinance_id' => $record->id,
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
        ?int $recordId = null,
    ): array {
        $query = AppropriationOrdinance::query()
            ->whereNotNull('pdf_url')
            ->where('pdf_url', '!=', '');

        if ($recordId !== null) {
            $query->whereKey($recordId);
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

        foreach ($query->cursor() as $record) {
            /** @var AppropriationOrdinance $record */
            if ($dryRun) {
                $skipped++;

                continue;
            }

            $result = $this->mirror($record, overwrite: ! $onlyMissing);

            if ($result['ok'] && str_contains($result['message'], 'skipped')) {
                $skipped++;
            } elseif ($result['ok']) {
                $mirrored++;
            } else {
                $failed++;
                $errors[] = 'AO #'.$record->id.': '.$result['message'];
            }
        }

        return compact('mirrored', 'skipped', 'failed', 'errors');
    }
}
