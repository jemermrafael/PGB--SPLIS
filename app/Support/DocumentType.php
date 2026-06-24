<?php

namespace App\Support;

class DocumentType
{
    public const RESOLUTION = 'resolution';

    public const ORDINANCE = 'ordinance';

    public static function label(?string $type): string
    {
        return match ($type) {
            self::ORDINANCE => 'Ordinance',
            default => 'Resolution',
        };
    }

    public static function badgeClass(?string $type): string
    {
        return match ($type) {
            self::ORDINANCE => 'splis-badge-doc-type splis-badge-doc-type--ordinance',
            default => 'splis-badge-doc-type splis-badge-doc-type--resolution',
        };
    }

    public static function infer(?string $resolutionNo, ?string $title): string
    {
        $normalizedTitle = strtoupper(trim($title ?? ''));

        if ($normalizedTitle !== '' && preg_match('/^(AN?\s+)?ORDINANCE\b/', $normalizedTitle)) {
            return self::ORDINANCE;
        }

        if (str_contains(strtoupper($resolutionNo ?? ''), '-O-')) {
            return self::ORDINANCE;
        }

        return self::RESOLUTION;
    }

    public static function forMigratedRecord(): string
    {
        return self::RESOLUTION;
    }
}
