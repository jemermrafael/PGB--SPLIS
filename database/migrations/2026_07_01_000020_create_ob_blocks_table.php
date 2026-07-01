<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ob_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ob_document_id')->constrained('ob_documents')->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('content');
            $table->foreignId('agenda_item_id')->nullable()->constrained('agenda_items')->nullOnDelete();
            $table->timestamps();

            $table->index(['ob_document_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ob_blocks');
    }
};
