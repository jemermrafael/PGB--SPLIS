<?php

namespace App\Support;

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
        return CommitteeOptions::names();
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
