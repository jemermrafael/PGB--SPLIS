<?php

namespace App\Support;

use Illuminate\Support\Collection;

class AgendaFieldOptions
{
    /**
     * @return list<string>
     */
    public static function senders(): array
    {
        return self::mergeStored('sender', config('agenda.sender_seeds', []));
    }

    /**
     * @return list<string>
     */
    public static function committees(): array
    {
        return self::mergeStored('committee_referred', config('agenda.committee_seeds', []));
    }

    /**
     * @return list<string>
     */
    public static function outcomes(): array
    {
        return config('agenda.outcomes', []);
    }

    /**
     * @param  list<string>  $seeds
     * @return list<string>
     */
    protected static function mergeStored(string $column, array $seeds): array
    {
        $stored = \App\Models\AgendaItem::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        return collect($seeds)
            ->merge($stored)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->sort()
            ->values()
            ->all();
    }
}
