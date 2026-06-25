<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->unsignedInteger('legacy_file_id')->nullable()->unique()->after('legacy_sp_id');
            $table->string('legacy_sp_res_no', 50)->nullable()->after('legacy_file_id');
            $table->unsignedInteger('sp_sequence')->nullable()->after('legacy_sp_res_no');
            $table->string('mun_resolution_no', 100)->nullable()->after('sp_sequence');
            $table->text('mun_title')->nullable()->after('mun_resolution_no');
            $table->string('mun_series', 20)->nullable()->after('mun_title');
            $table->date('date_received')->nullable()->after('mun_series');
            $table->string('action_taken', 100)->nullable()->after('date_received');
            $table->string('agenda', 150)->nullable()->after('action_taken');
            $table->string('concerned_agency', 150)->nullable()->after('agenda');
            $table->text('remarks')->nullable()->after('concerned_agency');
            $table->string('sp_pdf_url', 500)->nullable()->after('remarks');
            $table->string('mun_pdf_url', 500)->nullable()->after('sp_pdf_url');

            $table->index(['series', 'sp_sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->dropIndex(['series', 'sp_sequence']);
            $table->dropColumn([
                'legacy_file_id',
                'legacy_sp_res_no',
                'sp_sequence',
                'mun_resolution_no',
                'mun_title',
                'mun_series',
                'date_received',
                'action_taken',
                'agenda',
                'concerned_agency',
                'remarks',
                'sp_pdf_url',
                'mun_pdf_url',
            ]);
        });
    }
};
