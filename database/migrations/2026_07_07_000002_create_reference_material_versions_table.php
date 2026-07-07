<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_material_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reference_material_id')->constrained()->cascadeOnDelete();
            $table->string('version_no', 30);
            $table->string('file_path', 500);
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_material_id', 'created_at'], 'rmv_ref_created_idx');
            $table->unique(['reference_material_id', 'version_no'], 'rmv_ref_ver_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_material_versions');
    }
};

