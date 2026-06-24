<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->index();
            $table->string('description', 200);
            $table->timestamps();
        });

        Schema::create('category2s', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unsignedInteger('legacy_id')->nullable()->index();
            $table->string('description', 200);
            $table->timestamps();
        });

        Schema::create('category3s', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category2_id')->constrained('category2s')->cascadeOnDelete();
            $table->unsignedInteger('legacy_id')->nullable()->index();
            $table->string('description', 200);
            $table->timestamps();
        });

        Schema::create('category4s', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category3_id')->constrained('category3s')->cascadeOnDelete();
            $table->unsignedInteger('legacy_id')->nullable()->index();
            $table->string('description', 200);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code')->unique();
            $table->string('description', 200);
            $table->string('abbreviation', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code')->unique();
            $table->string('description', 200);
            $table->string('zipcode', 10)->nullable();
            $table->unsignedTinyInteger('district')->nullable();
            $table->timestamps();
        });

        Schema::create('series_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category4s');
        Schema::dropIfExists('category3s');
        Schema::dropIfExists('category2s');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('series_years');
    }
};
