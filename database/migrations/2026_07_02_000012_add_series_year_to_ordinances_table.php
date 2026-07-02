<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->unsignedSmallInteger('series_year')->default(2026)->after('ordinance_no');
        });

        DB::table('ordinances')->update(['series_year' => 2026]);

        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropUnique(['ordinance_no']);
            $table->unique(['ordinance_no', 'series_year']);
            $table->index('series_year');
        });
    }

    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropUnique(['ordinance_no', 'series_year']);
            $table->dropIndex(['series_year']);
            $table->dropColumn('series_year');
            $table->unique('ordinance_no');
        });
    }
};
