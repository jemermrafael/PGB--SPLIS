<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->string('pdf_url', 500)->nullable()->after('subject');
            $table->string('mov_bulletin_url', 500)->nullable()->after('mov_bulletin');
            $table->string('mov_certification_url', 500)->nullable()->after('mov_certification');
            $table->string('mov_newspaper_url', 500)->nullable()->after('mov_newspaper');
        });
    }

    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_url',
                'mov_bulletin_url',
                'mov_certification_url',
                'mov_newspaper_url',
            ]);
        });
    }
};
