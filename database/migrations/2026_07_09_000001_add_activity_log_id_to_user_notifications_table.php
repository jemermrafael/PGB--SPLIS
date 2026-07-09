<?php

use App\Models\ActivityLog;
use App\Services\ActivityLogNotifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignId('activity_log_id')
                ->nullable()
                ->after('agenda_item_id')
                ->constrained('activity_logs')
                ->nullOnDelete();

            $table->unique(['user_id', 'activity_log_id']);
        });

        $notifier = app(ActivityLogNotifier::class);

        ActivityLog::query()
            ->orderBy('id')
            ->chunkById(100, function ($logs) use ($notifier): void {
                foreach ($logs as $log) {
                    $notifier->notify($log);
                }
            });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'activity_log_id']);
            $table->dropConstrainedForeignId('activity_log_id');
        });
    }
};
