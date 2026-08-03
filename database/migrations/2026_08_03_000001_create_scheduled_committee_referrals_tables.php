<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_committee_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legislative_session_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('committee_referral_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scheduled_committee_referral_id');
            $table->foreignId('agenda_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('board_member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('scheduled_committee_referral_id', 'scr_deliveries_schedule_fk')
                ->references('id')
                ->on('scheduled_committee_referrals')
                ->cascadeOnDelete();

            $table->unique(
                ['scheduled_committee_referral_id', 'agenda_item_id', 'board_member_id'],
                'scr_delivery_unique',
            );
            $table->index(['board_member_id', 'delivered_at'], 'scr_delivery_bm_delivered_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_referral_deliveries');
        Schema::dropIfExists('scheduled_committee_referrals');
    }
};
