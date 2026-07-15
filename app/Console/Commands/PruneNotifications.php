<?php

namespace App\Console\Commands;

use App\Models\UserNotification;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'splis:prune-notifications
                            {--all : Delete all notifications instead of only those past retention}
                            {--days= : Override retention days when pruning expired}';

    protected $description = 'Delete user notifications older than the retention window (default 30 days)';

    public function handle(): int
    {
        if ($this->option('all')) {
            $deleted = UserNotification::query()->delete();
            $this->info("Cleared {$deleted} notification(s).");

            return self::SUCCESS;
        }

        $days = $this->option('days') !== null
            ? max(1, (int) $this->option('days'))
            : UserNotification::RETENTION_DAYS;

        $deleted = UserNotification::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} notification(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
