<?php

namespace App\Support;

use App\Models\IncomingDocument;
use Illuminate\Support\Collection;

class IncomingFieldOptions
{
    /**
     * @return list<string>
     */
    public static function actionTaken(): array
    {
        return config('incoming.action_taken', []);
    }

    /**
     * @return list<string>
     */
    public static function concernedAgencies(): array
    {
        return config('incoming.concerned_agencies', []);
    }

    /**
     * @return list<string>
     */
    public static function referrals(): array
    {
        $fromDatabase = IncomingDocument::query()
            ->selectRaw('referral, COUNT(*) as usage_count')
            ->whereNotNull('referral')
            ->where('referral', '!=', '')
            ->groupBy('referral')
            ->orderByDesc('usage_count')
            ->limit(60)
            ->pluck('referral');

        return self::uniqueSorted(
            collect(config('incoming.referral_seeds', []))->merge($fromDatabase)
        );
    }

    /**
     * @param  Collection<int, string>  $values
     * @return list<string>
     */
    protected static function uniqueSorted(Collection $values): array
    {
        $seen = [];
        $result = [];

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $key = strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $value;
        }

        sort($result, SORT_NATURAL | SORT_FLAG_CASE);

        return $result;
    }
}
