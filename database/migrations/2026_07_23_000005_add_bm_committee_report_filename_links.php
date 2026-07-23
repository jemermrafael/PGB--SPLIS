<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_member_committee_reports', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('pdf_path');
        });

        Schema::table('legislative_session_committee_report_files', function (Blueprint $table) {
            $table->unsignedBigInteger('board_member_committee_report_id')->nullable()->after('legislative_session_id');
            $table->foreign('board_member_committee_report_id', 'ls_crf_bm_report_fk')
                ->references('id')
                ->on('board_member_committee_reports')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legislative_session_committee_report_files', function (Blueprint $table) {
            $table->dropForeign('ls_crf_bm_report_fk');
            $table->dropColumn('board_member_committee_report_id');
        });

        Schema::table('board_member_committee_reports', function (Blueprint $table) {
            $table->dropColumn('original_filename');
        });
    }
};
