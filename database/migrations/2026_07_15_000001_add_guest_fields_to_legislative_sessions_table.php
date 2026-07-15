<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('notes');
            $table->text('guest_remarks')->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('legislative_sessions', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_remarks']);
        });
    }
};
