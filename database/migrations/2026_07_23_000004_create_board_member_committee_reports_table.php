<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_member_committee_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_member_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('pdf_path');
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('board_member_committee_report_agenda_item', function (Blueprint $table) {
            $table->unsignedBigInteger('board_member_committee_report_id');
            $table->foreign('board_member_committee_report_id', 'bmcr_agenda_report_fk')
                ->references('id')
                ->on('board_member_committee_reports')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('agenda_item_id');
            $table->foreign('agenda_item_id', 'bmcr_agenda_item_fk')
                ->references('id')
                ->on('agenda_items')
                ->cascadeOnDelete();
            $table->primary(['board_member_committee_report_id', 'agenda_item_id'], 'bmcr_agenda_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_member_committee_report_agenda_item');
        Schema::dropIfExists('board_member_committee_reports');
    }
};
