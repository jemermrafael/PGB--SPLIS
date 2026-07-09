<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->string('pdf_summary_committee_reports', 500)->nullable()->after('notes');
            $table->string('pdf_committee_reports', 500)->nullable()->after('pdf_summary_committee_reports');
            $table->string('pdf_draft_journal', 500)->nullable()->after('pdf_committee_reports');
            $table->string('pdf_draft_minutes', 500)->nullable()->after('pdf_draft_journal');
            $table->string('pdf_final_journal', 500)->nullable()->after('pdf_draft_minutes');
            $table->string('pdf_final_minutes', 500)->nullable()->after('pdf_final_journal');
        });
    }

    public function down(): void
    {
        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_summary_committee_reports',
                'pdf_committee_reports',
                'pdf_draft_journal',
                'pdf_draft_minutes',
                'pdf_final_journal',
                'pdf_final_minutes',
            ]);
        });
    }
};
