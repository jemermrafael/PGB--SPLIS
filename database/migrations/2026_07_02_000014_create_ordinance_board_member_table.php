<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordinance_board_member', function (Blueprint $table) {
            $table->foreignId('ordinance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->primary(['ordinance_id', 'board_member_id']);
        });

        Schema::table('ordinances', function (Blueprint $table) {
            $table->dropColumn(['authored_by', 'sponsored_by']);
        });
    }

    public function down(): void
    {
        Schema::table('ordinances', function (Blueprint $table) {
            $table->string('authored_by', 200)->nullable()->after('subject');
            $table->string('sponsored_by', 200)->nullable()->after('authored_by');
        });

        Schema::dropIfExists('ordinance_board_member');
    }
};
