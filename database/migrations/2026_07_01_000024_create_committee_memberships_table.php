<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_term_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['committee_id', 'committee_term_id', 'board_member_id', 'role'], 'committee_memberships_unique');
            $table->index(['committee_id', 'committee_term_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_memberships');
    }
};
