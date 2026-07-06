<?php

namespace App\Services;

use App\Models\IncomingDocument;

class IncomingDocumentImporter
{
    public function __construct(
        protected SptrackReader $reader,
    ) {}

    /**
     * @return array{total: int, created: int, updated: int, skipped: int}
     */
    public function importFromSptrack(?string $csvPath = null, string $source = 'database'): array
    {
        return $this->syncFromSptrack($csvPath, $source, dryRun: false);
    }

    /**
     * @return array{total: int, created: int, updated: int, skipped: int}
     */
    public function syncFromSptrack(?string $csvPath = null, string $source = 'database', bool $dryRun = false): array
    {
        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

        $this->reader->chunkFiles(500, function (array $chunk) use (&$stats, $dryRun) {
            foreach ($chunk as $row) {
                if ($row['legacy_file_id'] < 1) {
                    continue;
                }

                $stats['total']++;

                $existing = IncomingDocument::query()
                    ->where('legacy_file_id', $row['legacy_file_id'])
                    ->first();

                if ($existing) {
                    if ($dryRun) {
                        $stats['updated']++;

                        continue;
                    }

                    $existing->update($this->syncAttributes($row));
                    $stats['updated']++;

                    continue;
                }

                if ($dryRun) {
                    $stats['created']++;

                    continue;
                }

                IncomingDocument::create($this->mapRow($row));
                $stats['created']++;
            }
        }, $csvPath, $source);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function syncAttributes(array $row): array
    {
        return [
            'mun_resolution_no' => $row['mun_resolution_no'],
            'date_received' => $row['date_received'],
            'mun_series' => $row['mun_series'],
            'municipality' => $row['municipality'],
            'title' => $row['mun_title'] ?? $row['sp_title'],
            'action_taken' => $row['action_taken'],
            'referral' => $row['referral'],
            'agenda' => $row['agenda'],
            'workflow_status' => $row['sptrack_status'],
            'sp_res_no' => $row['sp_res_no'],
            'sp_series' => $row['sp_series'],
            'sp_title' => $row['sp_title'],
            'sp_date_approved' => $row['sp_date_approved'],
            'keyword' => $row['keyword'],
            'concerned_agency' => $row['concerned_agency'],
            'remarks' => $row['remarks'],
            'mun_pdf_url' => $row['mun_pdf_url'],
            'sp_pdf_url' => $row['sp_pdf_url'],
            'sp_rec_added' => $row['sp_rec_added'],
            'sp_rec_modified' => $row['sp_rec_modified'],
            'sp_rec_added_by' => $row['sp_rec_added_by'],
            'sp_rec_modified_by' => $row['sp_rec_modified_by'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapRow(array $row): array
    {
        return array_merge($this->syncAttributes($row), [
            'legacy_file_id' => $row['legacy_file_id'],
            'source' => IncomingDocument::SOURCE_SPTRACK,
            'link_status' => IncomingDocument::LINK_UNLINKED,
            'resolution_id' => null,
            'created_by' => null,
        ]);
    }
}
