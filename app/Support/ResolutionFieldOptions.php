<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Department;
use App\Models\Resolution;
use Illuminate\Support\Collection;

class ResolutionFieldOptions
{
    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return Category::forSelect()
            ->pluck('description')
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function departments(): array
    {
        return Department::forSelect()
            ->pluck('description')
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function sponsoredBy(): array
    {
        $fromDatabase = Resolution::query()
            ->selectRaw('sponsored_by, COUNT(*) as usage_count')
            ->whereNotNull('sponsored_by')
            ->where('sponsored_by', '!=', '')
            ->groupBy('sponsored_by')
            ->orderByDesc('usage_count')
            ->pluck('sponsored_by');

        return self::uniqueSorted($fromDatabase);
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
