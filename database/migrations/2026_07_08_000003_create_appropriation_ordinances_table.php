<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appropriation_ordinances', function (Blueprint $table) {
            $table->id();
            $table->date('date_received')->nullable();
            $table->text('subject');
            $table->unsignedSmallInteger('ordinance_no');
            $table->unsignedSmallInteger('series_year');
            $table->date('date_passed')->nullable();
            $table->date('date_approved')->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->foreignId('agenda_item_id')->nullable()->constrained('agenda_items')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ordinance_no', 'series_year'], 'ao_no_series_unique');
            $table->index('date_received');
            $table->index('date_passed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appropriation_ordinances');
    }
};
