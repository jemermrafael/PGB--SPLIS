<?php

namespace App\Support;

use App\Models\Committee;
use App\Models\CommitteeTermSecretary;
use Illuminate\Support\Collection;

class CommitteeSecretaryOptions
{
    /**
     * Distinct committee secretary names for combobox fields.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $fromLegacy = Committee::query()
            ->whereNotNull('secretary')
            ->where('secretary', '!=', '')
            ->pluck('secretary');

        $fromTerms = CommitteeTermSecretary::query()
            ->orderBy('name')
            ->pluck('name');

        return self::uniqueSorted($fromLegacy->merge($fromTerms));
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

            $key = mb_strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $value;
        }

        usort($result, fn (string $a, string $b) => strnatcasecmp($a, $b));

        return $result;
    }
}
