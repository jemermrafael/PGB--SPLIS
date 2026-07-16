<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->string('name', 200);
            $table->string('chair', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('vice_chair', 200)->nullable();
            $table->text('members')->nullable();
            $table->string('secretary', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committees');
    }
};
