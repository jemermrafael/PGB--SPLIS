<?php

use App\Models\BoardMember;
use App\Models\CommitteeTerm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_member_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_term_id')->constrained()->cascadeOnDelete();
            $table->string('district', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['board_member_id', 'committee_term_id'], 'board_member_terms_unique');
            $table->index(['committee_term_id', 'district']);
        });

        $currentTerm = CommitteeTerm::query()->current()->first()
            ?? CommitteeTerm::query()->orderByDesc('year_from')->first();

        if ($currentTerm === null) {
            return;
        }

        $districts = config('board_members.districts', []);

        BoardMember::query()
            ->whereIn('district', $districts)
            ->orderBy('id')
            ->each(function (BoardMember $member) use ($currentTerm): void {
                DB::table('board_member_terms')->insert([
                    'board_member_id' => $member->id,
                    'committee_term_id' => $currentTerm->id,
                    'district' => $member->district,
                    'is_active' => $member->is_active,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        $memberIdsWithDistrict = BoardMember::query()
            ->whereIn('district', $districts)
            ->pluck('id');

        DB::table('committee_memberships')
            ->where('committee_term_id', $currentTerm->id)
            ->whereNotIn('board_member_id', $memberIdsWithDistrict)
            ->distinct()
            ->pluck('board_member_id')
            ->each(function (int $boardMemberId) use ($currentTerm): void {
                DB::table('board_member_terms')->insertOrIgnore([
                    'board_member_id' => $boardMemberId,
                    'committee_term_id' => $currentTerm->id,
                    'district' => null,
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_member_terms');
    }
};
