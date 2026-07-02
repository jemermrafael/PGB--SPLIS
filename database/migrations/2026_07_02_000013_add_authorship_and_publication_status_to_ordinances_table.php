<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->string('authored_by', 200)->nullable()->after('subject');
            $table->string('sponsored_by', 200)->nullable()->after('authored_by');
            $table->string('publication_status', 30)->nullable()->after('sponsored_by');
            $table->index('publication_status');
        });
    }

    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropIndex(['publication_status']);
            $table->dropColumn(['authored_by', 'sponsored_by', 'publication_status']);
        });
    }
};
