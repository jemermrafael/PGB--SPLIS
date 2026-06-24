<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->unsignedInteger('legacy_sp_id')->nullable()->unique()->after('id');
            $table->index('legacy_sp_id');
        });
    }

    public function down(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            $table->dropIndex(['legacy_sp_id']);
            $table->dropColumn('legacy_sp_id');
        });
    }
};
