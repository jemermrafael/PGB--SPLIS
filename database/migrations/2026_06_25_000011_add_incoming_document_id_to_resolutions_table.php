<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->foreignId('incoming_document_id')
                ->nullable()
                ->unique()
                ->after('legacy_file_id')
                ->constrained('incoming_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('incoming_document_id');
        });
    }
};
