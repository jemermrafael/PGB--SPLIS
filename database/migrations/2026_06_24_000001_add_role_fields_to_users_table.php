<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('id');
            $table->string('role', 20)->default('encoder')->after('email');
            $table->unsignedInteger('legacy_user_id')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('legacy_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'legacy_user_id', 'is_active']);
        });
    }
};
