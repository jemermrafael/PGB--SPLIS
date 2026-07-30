<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->date('date_published_newspaper_end')->nullable()->after('date_published_newspaper');
        });
    }

    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn('date_published_newspaper_end');
        });
    }
};
