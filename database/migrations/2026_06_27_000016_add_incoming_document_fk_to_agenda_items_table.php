<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->foreign('incoming_document_id')
                ->references('id')
                ->on('incoming_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropForeign(['incoming_document_id']);
        });
    }
};
