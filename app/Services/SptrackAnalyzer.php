<?php

namespace App\Services;

use App\Models\SptrackImportQueue;
use Illuminate\Support\Str;

class SptrackAnalyzer
{
    public function __construct(
        protected SptrackReader $reader,
        protected SptrackMatcher $matcher,
    ) {}

    /**
     * @return array{batch_id: string, total: int, high: int, review: int, create: int, skip: int}
     */
    public function analyze(?string $csvPath = null, bool $fresh = true, string $source = 'database', ?int $limit = null): array
    {
        $batchId = (string) Str::uuid();
        $matcher = $this->matcher;

        if ($fresh) {
            SptrackImportQueue::query()
                ->whereIn('queue_status', [
                    SptrackImportQueue::STATUS_PENDING,
                    SptrackImportQueue::STATUS_APPROVED,
                ])
                ->delete();
        }

        $stats = ['high' => 0, 'review' => 0, 'create' => 0, 'skip' => 0];
        $upsertBatch = [];
        $now = now();
        $total = 0;
        $stop = false;

        $this->reader->chunkFiles(500, function (array $chunk) use (
            $matcher,
            $batchId,
            &$stats,
            &$upsertBatch,
            &$total,
            &$stop,
            $now,
            $limit,
        ) {
            foreach ($chunk as $row) {
                if ($stop) {
                    break;
                }

                if ($row['legacy_file_id'] < 1) {
                    continue;
                }

                $total++;
                if ($limit !== null && $total > $limit) {
                    $stop = true;
                    break;
                }
                $result = $matcher->analyze($row);
                $proposed = $result['proposed_action'];

                if ($proposed === SptrackImportQueue::ACTION_ENRICH && $result['confidence'] === SptrackImportQueue::CONFIDENCE_HIGH) {
                    $stats['high']++;
                } elseif ($proposed === SptrackImportQueue::ACTION_CREATE) {
                    $stats['create']++;
                } elseif ($proposed === SptrackImportQueue::ACTION_SKIP) {
                    $stats['skip']++;
                } else {
                    $stats['review']++;
                }

                $upsertBatch[] = array_merge($row, [
                    'batch_id' => $batchId,
                    'sp_sequence' => $result['sp_sequence'],
                    'suggested_resolution_id' => $result['suggested_resolution_id'],
                    'confidence' => $result['confidence'],
                    'match_signals' => json_encode($result['match_signals']),
                    'proposed_action' => $result['proposed_action'],
                    'notes' => $result['notes'],
                    'queue_status' => SptrackImportQueue::STATUS_PENDING,
                    'user_action' => null,
                    'user_resolution_id' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'applied_by' => null,
                    'applied_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (count($upsertBatch) >= 500) {
                $this->flushUpsertBatch($upsertBatch);
                $upsertBatch = [];
            }

            return ! $stop;
        }, $csvPath, $source);

        if ($upsertBatch !== []) {
            $this->flushUpsertBatch($upsertBatch);
        }

        return [
            'batch_id' => $batchId,
            'total' => $total,
            ...$stats,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    protected function flushUpsertBatch(array $batch): void
    {
        SptrackImportQueue::upsert(
            $batch,
            ['legacy_file_id'],
            [
                'batch_id',
                'sp_res_no',
                'sp_series',
                'sp_sequence',
                'sp_title',
                'sp_date_approved',
                'mun_resolution_no',
                'mun_title',
                'mun_series',
                'date_received',
                'municipality',
                'referral',
                'keyword',
                'sptrack_status',
                'action_taken',
                'agenda',
                'concerned_agency',
                'remarks',
                'sp_pdf_url',
                'mun_pdf_url',
                'sp_rec_added',
                'sp_rec_modified',
                'suggested_resolution_id',
                'confidence',
                'match_signals',
                'proposed_action',
                'notes',
                'queue_status',
                'user_action',
                'user_resolution_id',
                'reviewed_by',
                'reviewed_at',
                'applied_by',
                'applied_at',
                'updated_at',
            ]
        );
    }
}
