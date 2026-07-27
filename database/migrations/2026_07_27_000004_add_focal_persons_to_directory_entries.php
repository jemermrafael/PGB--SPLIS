<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_entries', function (Blueprint $table) {
            $table->json('focal_persons')->nullable()->after('emails');
        });
    }

    public function down(): void
    {
        Schema::table('directory_entries', function (Blueprint $table) {
            $table->dropColumn('focal_persons');
        });
    }
};
