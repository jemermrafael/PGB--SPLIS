<?php

namespace App\Services;

use App\Models\Ordinance;
use App\Support\OrdinancePdfType;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrdinancePdfMirrorService
{
    public function __construct(
        protected GoogleDrivePdfDownloader $downloader,
        protected OrdinancePdfService $pdfs,
    ) {}

    /**
     * Download a PDF URL into private storage and set its pdf_path column.
     *
     * @return array{ok: bool, message: string, path?: string, type?: string}
     */
    public function mirror(Ordinance $ordinance, string $type = OrdinancePdfType::MAIN, bool $overwrite = false): array
    {
        if (! OrdinancePdfType::isValid($type)) {
            return [
                'ok' => false,
                'message' => 'Unknown PDF type.',
                'type' => $type,
            ];
        }

        $config = OrdinancePdfType::config($type);

        if (! $overwrite && $this->pdfs->existsFor($ordinance, $type)) {
            return [
                'ok' => true,
                'message' => $config['label'].' already present locally; skipped.',
                'path' => $ordinance->{$config['path']},
                'type' => $type,
            ];
        }

        $url = trim((string) ($ordinance->{$config['url']} ?? ''));

        if ($url === '') {
            return [
                'ok' => false,
                'message' => 'No '.$config['label'].' URL to download.',
                'type' => $type,
            ];
        }

        try {
            $file = $this->downloader->downloadFile($url);
            $path = $this->pdfs->storeBytes(
                $file['contents'],
                (int) $ordinance->series_year,
                (int) $ordinance->ordinance_no,
                $type,
                $file['extension'],
            );
            $ordinance->update([$config['path'] => $path]);

            return [
                'ok' => true,
                'message' => $config['label'].' mirrored to '.$path,
                'path' => $path,
                'type' => $type,
            ];
        } catch (Throwable $e) {
            Log::warning('Ordinance PDF mirror failed', [
                'ordinance_id' => $ordinance->id,
                'type' => $type,
                'pdf_url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $config['label'].': '.$e->getMessage(),
                'type' => $type,
            ];
        }
    }

    /**
     * Mirror all PDF slots (main + MOV) that have URLs but no local file.
     *
     * @return array{mirrored: int, skipped: int, failed: int, messages: list<string>}
     */
    public function mirrorAllFor(Ordinance $ordinance, bool $overwrite = false): array
    {
        $mirrored = 0;
        $skipped = 0;
        $failed = 0;
        $messages = [];

        foreach (OrdinancePdfType::all() as $type) {
            $result = $this->mirror($ordinance, $type, $overwrite);

            if ($result['ok'] && str_contains($result['message'], 'skipped')) {
                $skipped++;
            } elseif ($result['ok']) {
                $mirrored++;
                $messages[] = $result['message'];
            } elseif (str_contains($result['message'], 'No ') && str_contains($result['message'], ' URL to download')) {
                // No URL for this slot — not a failure.
                continue;
            } else {
                $failed++;
                $messages[] = $result['message'];
            }
        }

        return compact('mirrored', 'skipped', 'failed', 'messages');
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
            ->where(function ($q): void {
                $q->where(function ($inner): void {
                    $inner->whereNotNull('pdf_url')->where('pdf_url', '!=', '');
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('mov_bulletin_url')->where('mov_bulletin_url', '!=', '');
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('mov_certification_url')->where('mov_certification_url', '!=', '');
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('mov_newspaper_url')->where('mov_newspaper_url', '!=', '');
                });
            });

        if ($ordinanceId !== null) {
            $query->whereKey($ordinanceId);
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
                $skipped += count($this->pdfs->missingMirrorTypes($ordinance));

                continue;
            }

            $result = $this->mirrorAllFor($ordinance, overwrite: ! $onlyMissing);

            $mirrored += $result['mirrored'];
            $skipped += $result['skipped'];
            $failed += $result['failed'];

            foreach ($result['messages'] as $message) {
                if (! $result['mirrored'] && $result['failed'] > 0) {
                    $errors[] = 'Ord #'.$ordinance->id.': '.$message;
                }
            }
        }

        return compact('mirrored', 'skipped', 'failed', 'errors');
    }
}
