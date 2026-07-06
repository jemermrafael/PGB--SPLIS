<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use App\Support\ActivityLogger;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'splis:backup-database';

    protected $description = 'Create a compressed MySQL dump and prune backups older than the retention period';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $file = $backups->create();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        ActivityLogger::log('backup.created', null, [
            'filename' => $file['filename'],
            'size' => $file['size'],
            'scheduled' => ! $this->input->isInteractive(),
        ], userId: null);

        $this->info("Backup created: {$file['filename']} ({$this->formatBytes($file['size'])})");

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
