<?php

namespace App\Support;

use App\Models\IncomingDocument;
use App\Models\Resolution;
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
     * @return list<string>
     */
    public static function committees(): array
    {
        $fromResolutions = Resolution::query()
            ->selectRaw('committee, COUNT(*) as usage_count')
            ->whereNotNull('committee')
            ->where('committee', '!=', '')
            ->groupBy('committee')
            ->orderByDesc('usage_count')
            ->limit(60)
            ->pluck('committee');

        $fromIncoming = IncomingDocument::query()
            ->selectRaw('referral, COUNT(*) as usage_count')
            ->whereNotNull('referral')
            ->where('referral', '!=', '')
            ->groupBy('referral')
            ->orderByDesc('usage_count')
            ->limit(60)
            ->pluck('referral');

        return self::uniqueSorted(
            collect(config('incoming.referral_seeds', []))
                ->merge($fromResolutions)
                ->merge($fromIncoming)
        );
    }

    /**
     * @return list<string>
     */
    public static function keywords(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('splis.keywords.used', 3600, function () {
            return self::buildKeywords();
        });
    }

    public static function forgetKeywordCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('splis.keywords.used');
    }

    /**
     * @return list<string>
     */
    protected static function buildKeywords(): array
    {
        $counts = [];

        $tally = function (?string $raw) use (&$counts): void {
            foreach (self::splitKeywords($raw) as $keyword) {
                $key = strtolower($keyword);

                if (! isset($counts[$key])) {
                    $counts[$key] = ['label' => $keyword, 'count' => 0];
                }

                $counts[$key]['count']++;
            }
        };

        IncomingDocument::query()
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '')
            ->select('keyword')
            ->cursor()
            ->each(fn ($row) => $tally($row->keyword));

        Resolution::query()
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '')
            ->select('keyword')
            ->cursor()
            ->each(fn ($row) => $tally($row->keyword));

        foreach (config('incoming.keyword_seeds', []) as $seed) {
            $keyword = trim((string) $seed);
            if ($keyword === '') {
                continue;
            }

            $key = strtolower($keyword);
            if (! isset($counts[$key])) {
                $counts[$key] = ['label' => $keyword, 'count' => 0];
            }
        }

        return collect($counts)
            ->sortBy([
                fn (array $item) => -$item['count'],
                fn (array $item) => strtolower($item['label']),
            ])
            ->pluck('label')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function splitKeywords(?string $value): array
    {
        return KeywordList::split($value);
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
