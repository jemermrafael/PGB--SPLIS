<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ordinances', 'deleted_at')) {
            Schema::table('ordinances', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('appropriation_ordinances', 'deleted_at')) {
            Schema::table('appropriation_ordinances', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('committees', 'deleted_at')) {
            Schema::table('committees', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('board_members', 'deleted_at')) {
            Schema::table('board_members', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ordinances', 'deleted_at')) {
            Schema::table('ordinances', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('appropriation_ordinances', 'deleted_at')) {
            Schema::table('appropriation_ordinances', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('committees', 'deleted_at')) {
            Schema::table('committees', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('board_members', 'deleted_at')) {
            Schema::table('board_members', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
