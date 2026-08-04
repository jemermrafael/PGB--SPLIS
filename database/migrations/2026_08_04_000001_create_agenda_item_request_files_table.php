<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_item_request_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agenda_item_id');
            $table->string('relative_folder', 500)->nullable();
            $table->string('original_filename');
            $table->string('stored_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('agenda_item_id', 'airf_agenda_fk')
                ->references('id')
                ->on('agenda_items')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'airf_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['agenda_item_id', 'relative_folder'], 'airf_agenda_folder_idx');
            $table->unique(['agenda_item_id', 'stored_path'], 'airf_agenda_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_item_request_files');
    }
};
