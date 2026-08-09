<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_member_committee_reports', function (Blueprint $table): void {
            $table->foreignId('legislative_session_id')
                ->nullable()
                ->after('board_member_id')
                ->constrained('legislative_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('board_member_committee_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('legislative_session_id');
        });
    }
};
