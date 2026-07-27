<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_backgrounds', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 80)->unique();
            $table->string('background_type', 20)->default('classic');
            $table->string('color', 32)->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('position', 40)->default('default');
            $table->string('attachment', 20)->default('default');
            $table->string('repeat', 20)->default('default');
            $table->string('size', 20)->default('default');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_backgrounds');
    }
};
