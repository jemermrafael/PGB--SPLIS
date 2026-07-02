<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordinances', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ordinance_no')->unique();
            $table->text('subject')->nullable();
            $table->date('date_enacted')->nullable();
            $table->date('date_approved')->nullable();
            $table->date('date_posted')->nullable();
            $table->date('date_published_newspaper')->nullable();
            $table->date('effectivity_date')->nullable();
            $table->text('mov_bulletin')->nullable();
            $table->string('mov_certification', 200)->nullable();
            $table->string('mov_newspaper', 200)->nullable();
            $table->text('implementing_bodies')->nullable();
            $table->string('classification', 100)->nullable();
            $table->string('mandate_ppa', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('date_enacted');
            $table->index('effectivity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordinances');
    }
};
