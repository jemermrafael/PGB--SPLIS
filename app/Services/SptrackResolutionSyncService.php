<?php

namespace App\Services;

use App\Models\Municipality;
use App\Models\Resolution;
use App\Support\ResolutionNumberParser;

class SptrackResolutionSyncService
{
    /** @var array<string, int> */
    protected array $municipalityLookup = [];

    public function __construct(
        protected SptrackReader $reader,
    ) {}

    /**
     * @return array{total: int, updated: int, skipped: int}
     */
    public function sync(?string $csvPath = null, string $source = 'database', bool $dryRun = false): array
    {
        $this->municipalityLookup = Municipality::query()
            ->get(['id', 'description'])
            ->mapWithKeys(fn (Municipality $m) => [strtoupper(trim($m->description)) => $m->id])
            ->all();

        $stats = ['total' => 0, 'updated' => 0, 'skipped' => 0];

        $this->reader->chunkFiles(500, function (array $chunk) use (&$stats, $dryRun) {
            foreach ($chunk as $row) {
                if (($row['legacy_file_id'] ?? 0) < 1) {
                    continue;
                }

                $stats['total']++;

                $resolution = Resolution::query()
                    ->where('legacy_file_id', $row['legacy_file_id'])
                    ->first();

                if (! $resolution) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $stats['updated']++;

                    continue;
                }

                $resolution->update($this->buildPayload($row));
                $stats['updated']++;
            }
        }, $csvPath, $source);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function buildPayload(array $row): array
    {
        $sequence = ResolutionNumberParser::parseSpResNo($row['sp_res_no'] ?? null)['sequence'];

        $payload = [
            'legacy_sp_res_no' => $row['sp_res_no'],
            'sp_sequence' => $sequence,
            'mun_resolution_no' => $row['mun_resolution_no'],
            'mun_title' => $row['mun_title'],
            'mun_series' => $row['mun_series'],
            'date_received' => $row['date_received'],
            'action_taken' => $row['action_taken'],
            'agenda' => $row['agenda'],
            'concerned_agency' => $row['concerned_agency'],
            'remarks' => $row['remarks'],
            'sp_pdf_url' => $row['sp_pdf_url'],
            'mun_pdf_url' => $row['mun_pdf_url'],
            'keyword' => $row['keyword'],
            'committee' => $row['referral'],
        ];

        if (! empty($row['sp_title'])) {
            $payload['resolution_title'] = $row['sp_title'];
        }

        if (! empty($row['sp_date_approved'])) {
            $payload['date_approved'] = $row['sp_date_approved'];
        }

        if (! empty($row['sptrack_status'])) {
            $payload['status'] = $this->mapStatus($row['sptrack_status']);
        }

        $municipalityId = $this->resolveMunicipalityId($row['municipality'] ?? null);
        if ($municipalityId) {
            $payload['municipality_id'] = $municipalityId;
        }

        return $payload;
    }

    protected function resolveMunicipalityId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        $key = strtoupper(trim($name));
        if (isset($this->municipalityLookup[$key])) {
            return $this->municipalityLookup[$key];
        }

        foreach ($this->municipalityLookup as $label => $id) {
            if (str_contains($key, $label) || str_contains($label, $key)) {
                return $id;
            }
        }

        return null;
    }

    protected function mapStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match (true) {
            str_contains($status, 'approved') => 'approved',
            str_contains($status, 'agenda') => 'pending',
            default => 'draft',
        };
    }
}
