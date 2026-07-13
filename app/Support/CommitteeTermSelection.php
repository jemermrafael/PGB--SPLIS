<?php

namespace App\Support;

use App\Models\CommitteeTerm;
use Illuminate\Support\Collection;

class CommitteeTermSelection
{
    /**
     * @return array{terms: Collection<int, CommitteeTerm>, selectedTerm: CommitteeTerm}
     */
    public static function resolve(?int $requestedTermId = null): array
    {
        $terms = CommitteeTerm::query()->ordered()->get();

        if ($terms->isEmpty()) {
            $current = CommitteeTerm::currentOrCreate();

            return [
                'terms' => collect([$current]),
                'selectedTerm' => $current,
            ];
        }

        $selectedTermId = $requestedTermId
            ?: $terms->firstWhere('is_current', true)?->id
            ?: $terms->first()->id;

        $selectedTerm = $terms->firstWhere('id', $selectedTermId) ?? $terms->first();

        return [
            'terms' => $terms,
            'selectedTerm' => $selectedTerm,
        ];
    }

    public static function current(): CommitteeTerm
    {
        return self::resolve(null)['selectedTerm'];
    }
}
