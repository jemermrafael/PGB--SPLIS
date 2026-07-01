<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislative_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('session_date')->index();
            $table->time('session_time')->nullable();
            $table->string('session_number', 120)->nullable();
            $table->string('session_kind', 30)->default('regular')->index();
            $table->string('venue', 200)->nullable();
            $table->foreignId('prior_session_id')->nullable()->constrained('legislative_sessions')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislative_sessions');
    }
};
