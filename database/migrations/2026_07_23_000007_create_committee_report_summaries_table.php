<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_report_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legislative_session_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('SUMMARY OF COMMITTEE REPORT');
            $table->date('report_date')->nullable();
            $table->json('content')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('legislative_session_id', 'crs_session_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_report_summaries');
    }
};
