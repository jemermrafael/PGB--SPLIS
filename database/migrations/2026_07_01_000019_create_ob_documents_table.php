<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ob_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legislative_session_id')->unique()->constrained('legislative_sessions')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('next_session_agenda_no')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ob_documents');
    }
};
