<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_icon_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 80)->unique();
            $table->foreignId('icon_library_id')
                ->constrained('icon_library_items')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_icon_overrides');
    }
};
