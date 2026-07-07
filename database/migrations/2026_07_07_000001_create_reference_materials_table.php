<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_materials', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('document_type', 50)->index();
            $table->string('reference_no', 120)->nullable()->index();
            $table->string('issuing_office', 200)->nullable()->index();
            $table->date('date_issued')->nullable()->index();
            $table->date('effective_date')->nullable();
            $table->text('summary')->nullable();
            $table->string('keywords', 500)->nullable();
            $table->string('version_no', 30)->nullable();
            $table->foreignId('supersedes_reference_material_id')
                ->nullable()
                ->constrained('reference_materials')
                ->nullOnDelete();

            $table->string('file_path', 500)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->string('status', 30)->default('active')->index();
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_materials');
    }
};

