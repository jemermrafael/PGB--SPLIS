<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('municipality_id')
                ->nullable()
                ->after('board_member_id')
                ->constrained('municipalities')
                ->nullOnDelete();

            $table->unique('municipality_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['municipality_id']);
            $table->dropConstrainedForeignId('municipality_id');
        });
    }
};
