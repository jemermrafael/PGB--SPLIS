<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->string('ob_lifecycle_stage', 30)->nullable()->after('created_by');
            $table->timestamp('ob_manual_override_at')->nullable()->after('ob_lifecycle_stage');
            $table->foreignId('last_ob_synced_session_id')
                ->nullable()
                ->after('ob_manual_override_at')
                ->constrained('legislative_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_ob_synced_session_id');
            $table->dropColumn(['ob_lifecycle_stage', 'ob_manual_override_at']);
        });
    }
};
