<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icon_library_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('committees', function (Blueprint $table) {
            $table->foreignId('icon_library_id')
                ->nullable()
                ->after('icon_path')
                ->constrained('icon_library_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('icon_library_id');
        });

        Schema::dropIfExists('icon_library_items');
    }
};
