<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resolutions', function (Blueprint $table) {
            $table->id();
            $table->string('resolution_no', 50);
            $table->text('resolution_title');
            $table->unsignedSmallInteger('series')->index();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('date_approved')->nullable();
            $table->string('sponsored_by', 100)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('category2_id')->nullable()->constrained('category2s')->nullOnDelete();
            $table->foreignId('category3_id')->nullable()->constrained('category3s')->nullOnDelete();
            $table->foreignId('category4_id')->nullable()->constrained('category4s')->nullOnDelete();
            $table->string('keyword', 100)->nullable();
            $table->string('committee', 100)->nullable();
            $table->string('app_ord_no', 20)->nullable();
            $table->unsignedInteger('amount')->nullable();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->boolean('province')->default(false);
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resolution_no', 'series']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resolutions');
    }
};
