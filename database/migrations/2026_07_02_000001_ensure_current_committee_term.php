<?php

use App\Models\CommitteeTerm;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = config('board_members.current_term', []);

        if ($defaults === []) {
            return;
        }

        CommitteeTerm::query()->update(['is_current' => false]);

        $term = CommitteeTerm::query()
            ->where('year_from', $defaults['year_from'] ?? null)
            ->where('year_to', $defaults['year_to'] ?? null)
            ->first();

        if ($term !== null) {
            $term->update([
                'label' => $defaults['label'],
                'is_current' => true,
            ]);

            return;
        }

        CommitteeTerm::query()->create([
            'label' => $defaults['label'],
            'year_from' => $defaults['year_from'] ?? null,
            'year_to' => $defaults['year_to'] ?? null,
            'is_current' => true,
        ]);
    }

    public function down(): void
    {
        //
    }
};
