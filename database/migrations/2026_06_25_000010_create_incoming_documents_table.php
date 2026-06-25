<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_file_id')->nullable()->unique();
            $table->string('source', 20)->default('manual');
            $table->string('link_status', 20)->default('unlinked')->index();
            $table->foreignId('resolution_id')->nullable()->unique()->constrained('resolutions')->nullOnDelete();
            $table->string('mun_resolution_no', 100)->nullable();
            $table->date('date_received')->nullable();
            $table->string('mun_series', 20)->nullable();
            $table->string('municipality', 100)->nullable();
            $table->text('title')->nullable();
            $table->string('action_taken', 100)->nullable();
            $table->string('referral', 150)->nullable();
            $table->string('agenda', 150)->nullable();
            $table->string('workflow_status', 50)->nullable();
            $table->string('sp_res_no', 50)->nullable();
            $table->unsignedSmallInteger('sp_series')->nullable()->index();
            $table->text('sp_title')->nullable();
            $table->date('sp_date_approved')->nullable();
            $table->string('keyword', 150)->nullable();
            $table->string('concerned_agency', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->string('mun_pdf_url', 500)->nullable();
            $table->string('sp_pdf_url', 500)->nullable();
            $table->timestamp('sp_rec_modified')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sp_res_no', 'sp_series']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_documents');
    }
};
