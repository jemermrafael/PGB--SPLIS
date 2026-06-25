<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sptrack_import_queue', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 36)->index();
            $table->unsignedInteger('legacy_file_id')->unique();
            $table->string('sp_res_no', 50)->nullable();
            $table->unsignedSmallInteger('sp_series')->nullable()->index();
            $table->unsignedInteger('sp_sequence')->nullable();
            $table->text('sp_title')->nullable();
            $table->date('sp_date_approved')->nullable();
            $table->string('mun_resolution_no', 100)->nullable();
            $table->text('mun_title')->nullable();
            $table->string('mun_series', 20)->nullable();
            $table->date('date_received')->nullable();
            $table->string('municipality', 100)->nullable();
            $table->string('referral', 150)->nullable();
            $table->string('keyword', 150)->nullable();
            $table->string('sptrack_status', 50)->nullable();
            $table->string('action_taken', 100)->nullable();
            $table->string('agenda', 150)->nullable();
            $table->string('concerned_agency', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->string('sp_pdf_url', 500)->nullable();
            $table->string('mun_pdf_url', 500)->nullable();
            $table->timestamp('sp_rec_modified')->nullable();
            $table->foreignId('suggested_resolution_id')->nullable()->constrained('resolutions')->nullOnDelete();
            $table->string('confidence', 20)->default('none')->index();
            $table->json('match_signals')->nullable();
            $table->string('proposed_action', 20)->default('skip')->index();
            $table->string('user_action', 20)->nullable();
            $table->foreignId('user_resolution_id')->nullable()->constrained('resolutions')->nullOnDelete();
            $table->string('queue_status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sp_series', 'sp_sequence']);
            $table->index(['batch_id', 'queue_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sptrack_import_queue');
    }
};
