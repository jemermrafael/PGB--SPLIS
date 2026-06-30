<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_items', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no', 20)->nullable()->unique();
            $table->string('request_pdf_url', 500)->nullable();
            $table->date('date_received')->nullable()->index();
            $table->time('time_received')->nullable();
            $table->unsignedTinyInteger('prescribed_days')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->string('days_left_label', 50)->nullable();
            $table->string('sender', 150)->nullable()->index();
            $table->text('title')->nullable();
            $table->string('committee_referred', 200)->nullable();
            $table->date('date_of_referral')->nullable();
            $table->date('date_of_committee_meeting')->nullable();
            $table->string('committee_meeting_minutes', 200)->nullable();
            $table->string('outcome', 80)->nullable();
            $table->string('committee_report_url', 500)->nullable();
            $table->date('date_passed')->nullable();
            $table->date('date_signed_by_gov')->nullable();
            $table->string('reso_ord_ao_no', 50)->nullable()->index();
            $table->unsignedSmallInteger('reso_ord_ao_series')->nullable()->index();
            $table->string('reso_ord_ao_type', 40)->nullable();
            $table->string('reso_ord_ao_url', 500)->nullable();
            $table->foreignId('resolution_id')->nullable()->constrained('resolutions')->nullOnDelete();
            $table->text('resolution_title')->nullable();
            $table->string('journal_url', 500)->nullable();
            $table->string('minutes_url', 500)->nullable();
            $table->text('remarks')->nullable();
            $table->string('gdrive_folder_url', 500)->nullable();
            $table->unsignedBigInteger('incoming_document_id')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['reso_ord_ao_no', 'reso_ord_ao_series']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_items');
    }
};
