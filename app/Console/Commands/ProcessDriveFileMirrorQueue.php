<?php

namespace App\Console\Commands;

use App\Services\DriveFileMirrorQueueService;
use Illuminate\Console\Command;

class ProcessDriveFileMirrorQueue extends Command
{
    protected $signature = 'pdf-mirror:process-queue
                            {--rebuild : Scan records and refresh the queue first}
                            {--limit=5 : Number of pending items to process (0 = none)}
                            {--dry-run : Rebuild only; do not download}';

    protected $description = 'Process pending Google Drive PDF mirror queue items';

    public function handle(DriveFileMirrorQueueService $queue): int
    {
        if ($this->option('rebuild')) {
            $stats = $queue->rebuildQueue();
            $this->info(sprintf(
                'Queue rebuilt — %d enqueued/reset, %d marked completed, %d removed.',
                $stats['enqueued'],
                $stats['completed'],
                $stats['removed'],
            ));
        }

        $limit = max(0, (int) $this->option('limit'));

        if ($this->option('dry-run')) {
            $counts = $queue->stats();
            $this->table(['Status', 'Count'], [
                ['Pending', $counts['pending']],
                ['Processing', $counts['processing']],
                ['Completed', $counts['completed']],
                ['Failed', $counts['failed']],
            ]);

            return self::SUCCESS;
        }

        if ($limit === 0) {
            $this->comment('No items processed (limit is 0).');

            return self::SUCCESS;
        }

        $result = $queue->processBatch($limit);

        $this->table(['Metric', 'Count'], [
            ['Processed', $result['processed']],
            ['Succeeded', $result['succeeded']],
            ['Failed', $result['failed']],
        ]);

        return $result['failed'] > 0 && $result['succeeded'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
