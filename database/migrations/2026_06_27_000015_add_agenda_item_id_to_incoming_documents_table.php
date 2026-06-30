<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_documents', function (Blueprint $table) {
            $table->foreignId('agenda_item_id')
                ->nullable()
                ->unique()
                ->after('legacy_file_id')
                ->constrained('agenda_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incoming_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agenda_item_id');
        });
    }
};
