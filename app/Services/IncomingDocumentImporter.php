<?php

namespace App\Services;

use App\Models\IncomingDocument;

class IncomingDocumentImporter
{
    public function __construct(
        protected SptrackReader $reader,
    ) {}

    /**
     * @return array{imported: int, skipped: int, total: int}
     */
    public function importFromSptrack(?string $csvPath = null, string $source = 'database'): array
    {
        $imported = 0;
        $skipped = 0;
        $total = 0;

        $this->reader->chunkFiles(500, function (array $chunk) use (&$imported, &$skipped, &$total) {
            foreach ($chunk as $row) {
                if ($row['legacy_file_id'] < 1) {
                    continue;
                }

                $total++;

                if (IncomingDocument::query()->where('legacy_file_id', $row['legacy_file_id'])->exists()) {
                    $skipped++;

                    continue;
                }

                IncomingDocument::create($this->mapRow($row));
                $imported++;
            }
        }, $csvPath, $source);

        return compact('imported', 'skipped', 'total');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapRow(array $row): array
    {
        return [
            'legacy_file_id' => $row['legacy_file_id'],
            'source' => IncomingDocument::SOURCE_SPTRACK,
            'link_status' => IncomingDocument::LINK_UNLINKED,
            'resolution_id' => null,
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
            'sp_rec_modified' => $row['sp_rec_modified'],
            'created_by' => null,
        ];
    }
}
