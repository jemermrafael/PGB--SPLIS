<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislative_session_committee_report_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legislative_session_id');
            $table->string('original_filename');
            $table->string('stored_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('legislative_session_id', 'ls_crf_session_fk')
                ->references('id')
                ->on('legislative_sessions')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'ls_crf_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislative_session_committee_report_files');
    }
};
