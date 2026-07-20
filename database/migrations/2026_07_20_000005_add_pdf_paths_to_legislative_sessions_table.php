<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->string('pdf_summary_committee_reports_path', 500)->nullable()->after('pdf_summary_committee_reports');
            $table->string('pdf_draft_journal_path', 500)->nullable()->after('pdf_draft_journal');
            $table->string('pdf_draft_minutes_path', 500)->nullable()->after('pdf_draft_minutes');
            $table->string('pdf_final_journal_path', 500)->nullable()->after('pdf_final_journal');
            $table->string('pdf_final_minutes_path', 500)->nullable()->after('pdf_final_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_summary_committee_reports_path',
                'pdf_draft_journal_path',
                'pdf_draft_minutes_path',
                'pdf_final_journal_path',
                'pdf_final_minutes_path',
            ]);
        });
    }
};
