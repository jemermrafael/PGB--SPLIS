<?php

namespace App\Console\Commands;

use App\Models\SptrackImportQueue;
use App\Services\SptrackApplier;
use Illuminate\Console\Command;

class ApplySptrackQueue extends Command
{
    protected $signature = 'splis:apply-sptrack-queue
                            {--batch= : Limit to a specific analyze batch ID}
                            {--only-approved : Only apply rows already marked approved (default behaviour)}';

    protected $description = 'Apply approved sptrack migration queue rows to SPLIS resolutions';

    public function handle(SptrackApplier $applier): int
    {
        $pendingApproved = SptrackImportQueue::query()
            ->where('queue_status', SptrackImportQueue::STATUS_APPROVED)
            ->when($this->option('batch'), fn ($q, $batch) => $q->where('batch_id', $batch))
            ->count();

        if ($pendingApproved === 0) {
            $this->warn('No approved queue rows to apply. Approve rows in the migration UI first.');

            return self::SUCCESS;
        }

        $this->info("Applying {$pendingApproved} approved queue row(s)...");

        $stats = $applier->apply(batchId: $this->option('batch'));

        $this->table(
            ['Enriched', 'Created', 'Skipped', 'Failed'],
            [[$stats['enriched'], $stats['created'], $stats['skipped'], $stats['failed']]]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
