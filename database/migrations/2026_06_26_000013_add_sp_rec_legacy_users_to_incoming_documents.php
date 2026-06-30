<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_documents', function (Blueprint $table) {
            $table->string('sp_rec_added_by', 100)->nullable()->after('sp_rec_added');
            $table->string('sp_rec_modified_by', 100)->nullable()->after('sp_rec_modified');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_documents', function (Blueprint $table) {
            $table->dropColumn(['sp_rec_added_by', 'sp_rec_modified_by']);
        });
    }
};
