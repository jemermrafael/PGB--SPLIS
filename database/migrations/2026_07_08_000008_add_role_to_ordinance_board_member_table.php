<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordinance_board_member', function (Blueprint $table) {
            $table->dropForeign(['ordinance_id']);
            $table->dropForeign(['board_member_id']);
        });

        Schema::table('ordinance_board_member', function (Blueprint $table) {
            $table->dropPrimary(['ordinance_id', 'board_member_id']);
        });

        Schema::table('ordinance_board_member', function (Blueprint $table) {
            $table->string('role', 30)->default('authored_sponsored')->after('board_member_id');
        });

        DB::table('ordinance_board_member')->update(['role' => 'authored_sponsored']);

        Schema::table('ordinance_board_member', function (Blueprint $table) {
            $table->primary(['ordinance_id', 'board_member_id', 'role'], 'ordinance_board_member_primary');
            $table->foreign('ordinance_id')->references('id')->on('ordinances')->cascadeOnDelete();
            $table->foreign('board_member_id')->references('id')->on('board_members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ordinance_board_member', function (Blueprint $table) {
            $table->dropForeign(['ordinance_id']);
            $table->dropForeign(['board_member_id']);
            $table->dropPrimary('ordinance_board_member_primary');
        });

        DB::table('ordinance_board_member')
            ->orderBy('ordinance_id')
            ->orderBy('board_member_id')
            ->orderBy('role')
            ->get()
            ->groupBy(fn ($row) => $row->ordinance_id.'-'.$row->board_member_id)
            ->each(function ($rows): void {
                $rows->slice(1)->each(fn ($row) => DB::table('ordinance_board_member')
                    ->where('ordinance_id', $row->ordinance_id)
                    ->where('board_member_id', $row->board_member_id)
                    ->where('role', $row->role)
                    ->delete());
            });

        Schema::table('ordinance_board_member', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->primary(['ordinance_id', 'board_member_id']);
            $table->foreign('ordinance_id')->references('id')->on('ordinances')->cascadeOnDelete();
            $table->foreign('board_member_id')->references('id')->on('board_members')->cascadeOnDelete();
        });
    }
};
