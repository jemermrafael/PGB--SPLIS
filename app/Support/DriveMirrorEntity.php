<?php

namespace App\Support;

final class DriveMirrorEntity
{
    public const ORDINANCE = 'ordinance';

    public const APPROPRIATION_ORDINANCE = 'appropriation_ordinance';

    public const AGENDA_ITEM = 'agenda_item';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ORDINANCE,
            self::APPROPRIATION_ORDINANCE,
            self::AGENDA_ITEM,
        ];
    }

    public static function label(string $entityType): string
    {
        return match ($entityType) {
            self::ORDINANCE => 'Ordinance',
            self::APPROPRIATION_ORDINANCE => 'Appropriation Ordinance',
            self::AGENDA_ITEM => 'Agenda item',
            default => ucfirst(str_replace('_', ' ', $entityType)),
        };
    }
}
