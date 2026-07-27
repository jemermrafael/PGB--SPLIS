<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_members', function (Blueprint $table) {
            $table->json('focal_persons')->nullable()->after('mobile_number');
        });
    }

    public function down(): void
    {
        Schema::table('board_members', function (Blueprint $table) {
            $table->dropColumn('focal_persons');
        });
    }
};
