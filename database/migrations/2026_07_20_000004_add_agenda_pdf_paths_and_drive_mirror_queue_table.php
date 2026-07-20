<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->string('request_pdf_path', 500)->nullable()->after('request_pdf_url');
            $table->string('committee_report_pdf_path', 500)->nullable()->after('committee_report_url');
            $table->string('reso_ord_ao_pdf_path', 500)->nullable()->after('reso_ord_ao_url');
            $table->string('journal_pdf_path', 500)->nullable()->after('journal_url');
            $table->string('minutes_pdf_path', 500)->nullable()->after('minutes_url');
        });

        Schema::create('drive_file_mirror_queue', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('document_slot', 40);
            $table->string('source_url', 500);
            $table->string('status', 20)->default('pending');
            $table->string('result_path', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'document_slot'], 'drive_mirror_queue_unique');
            $table->index(['status', 'id']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_file_mirror_queue');

        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropColumn([
                'request_pdf_path',
                'committee_report_pdf_path',
                'reso_ord_ao_pdf_path',
                'journal_pdf_path',
                'minutes_pdf_path',
            ]);
        });
    }
};
