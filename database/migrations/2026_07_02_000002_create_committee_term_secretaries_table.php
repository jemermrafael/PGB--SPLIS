<?php

use App\Enums\CommitteeMembershipRole;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\CommitteeTermSecretary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_term_secretaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_term_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['committee_id', 'committee_term_id']);
        });

        CommitteeMembership::query()
            ->where('role', CommitteeMembershipRole::Secretary)
            ->with('boardMember')
            ->orderBy('id')
            ->each(function (CommitteeMembership $membership): void {
                $name = trim($membership->boardMember?->displayName() ?? '');

                if ($name === '') {
                    return;
                }

                CommitteeTermSecretary::query()->updateOrCreate(
                    [
                        'committee_id' => $membership->committee_id,
                        'committee_term_id' => $membership->committee_term_id,
                    ],
                    ['name' => $name],
                );
            });

        Committee::withoutGlobalScopes()
            ->whereNotNull('secretary')
            ->where('secretary', '!=', '')
            ->each(function ($committee): void {
                $termId = CommitteeTerm::query()->current()->value('id');

                if ($termId === null) {
                    return;
                }

                CommitteeTermSecretary::query()->updateOrCreate(
                    [
                        'committee_id' => $committee->id,
                        'committee_term_id' => $termId,
                    ],
                    ['name' => trim((string) $committee->secretary)],
                );
            });

        CommitteeMembership::query()
            ->where('role', CommitteeMembershipRole::Secretary)
            ->delete();

        $districts = config('board_members.districts', []);

        BoardMember::withoutGlobalScopes()
            ->where(function ($query) use ($districts): void {
                $query
                    ->whereNull('district')
                    ->orWhere('district', '')
                    ->when($districts !== [], fn ($inner) => $inner->orWhereNotIn('district', $districts));
            })
            ->whereDoesntHave('committeeMemberships')
            ->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_term_secretaries');
    }
};
