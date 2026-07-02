<?php

namespace App\Support;

use App\Models\AgendaItem;
use App\Models\Committee;
use Illuminate\Support\Collection;

class CommitteeOptions
{
    /**
     * @return list<array{id: int, name: string, chair: string|null}>
     */
    public static function forSelect(): array
    {
        return Committee::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'chair'])
            ->map(fn (Committee $committee) => [
                'id' => $committee->id,
                'name' => $committee->name,
                'chair' => $committee->chairDisplayName() ?: $committee->chair,
            ])
            ->values()
            ->all();
    }

    /**
     * Committee names for combobox fields (agenda, resolutions, etc.).
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $fromDatabase = Committee::query()
            ->active()
            ->ordered()
            ->pluck('name');

        $fromAgenda = AgendaItem::query()
            ->whereNotNull('committee_referred')
            ->where('committee_referred', '!=', '')
            ->distinct()
            ->orderBy('committee_referred')
            ->pluck('committee_referred');

        return self::uniqueSorted($fromDatabase->merge($fromAgenda));
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
