<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->string('mov_bulletin_pdf_path', 500)->nullable()->after('mov_bulletin_url');
            $table->string('mov_certification_pdf_path', 500)->nullable()->after('mov_certification_url');
            $table->string('mov_newspaper_pdf_path', 500)->nullable()->after('mov_newspaper_url');
        });
    }

    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn([
                'mov_bulletin_pdf_path',
                'mov_certification_pdf_path',
                'mov_newspaper_pdf_path',
            ]);
        });
    }
};
