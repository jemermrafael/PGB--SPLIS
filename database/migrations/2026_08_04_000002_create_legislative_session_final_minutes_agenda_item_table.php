<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislative_session_final_minutes_agenda_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legislative_session_id');
            $table->unsignedBigInteger('agenda_item_id');
            $table->timestamps();

            $table->unique(
                ['legislative_session_id', 'agenda_item_id'],
                'ls_fm_agenda_unique'
            );
            $table->foreign('legislative_session_id', 'ls_fm_session_fk')
                ->references('id')
                ->on('legislative_sessions')
                ->cascadeOnDelete();
            $table->foreign('agenda_item_id', 'ls_fm_agenda_fk')
                ->references('id')
                ->on('agenda_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislative_session_final_minutes_agenda_item');
    }
};
