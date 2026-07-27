<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('board_member_terms')->where('is_active', false)->update(['is_active' => true]);
        DB::table('board_members')->where('is_active', false)->update(['is_active' => true]);
    }

    public function down(): void
    {
        // Intentionally empty — cannot restore previous inactive flags.
    }
};
