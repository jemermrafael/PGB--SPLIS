<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_documents', function (Blueprint $table) {
            $table->timestamp('sp_rec_added')->nullable()->after('sp_rec_modified');
        });

        Schema::table('sptrack_import_queue', function (Blueprint $table) {
            $table->timestamp('sp_rec_added')->nullable()->after('mun_pdf_url');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_documents', function (Blueprint $table) {
            $table->dropColumn('sp_rec_added');
        });

        Schema::table('sptrack_import_queue', function (Blueprint $table) {
            $table->dropColumn('sp_rec_added');
        });
    }
};
