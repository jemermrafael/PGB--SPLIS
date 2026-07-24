<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->string('icon_key', 40)->nullable()->after('is_active');
            $table->string('icon_path', 255)->nullable()->after('icon_key');
        });
    }

    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropColumn(['icon_key', 'icon_path']);
        });
    }
};
