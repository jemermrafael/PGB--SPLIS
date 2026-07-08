<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legislative_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_member_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_present')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['legislative_session_id', 'board_member_id'], 'session_attendance_unique');
            $table->index('legislative_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_attendances');
    }
};
