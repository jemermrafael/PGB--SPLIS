<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_member_terms', function (Blueprint $table) {
            $table->string('ex_officio_title', 150)->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('board_member_terms', function (Blueprint $table) {
            $table->dropColumn('ex_officio_title');
        });
    }
};
