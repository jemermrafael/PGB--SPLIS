<?php

namespace App\Enums;

enum OrdinancePublicationStatus: string
{
    case Published = 'published';
    case ForPublication = 'for_publication';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'Published',
            self::ForPublication => 'For Publication',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Published => 'splis-badge-ordinance-published',
            self::ForPublication => 'splis-badge-ordinance-for-publication',
        };
    }

    public function markerDotClass(): string
    {
        return match ($this) {
            self::Published => 'splis-ordinance-marker-dot splis-ordinance-marker-dot--published',
            self::ForPublication => 'splis-ordinance-marker-dot splis-ordinance-marker-dot--for-publication',
        };
    }

    public function panelClass(): string
    {
        return match ($this) {
            self::Published => 'splis-ordinance-publication-panel splis-ordinance-publication-panel--published',
            self::ForPublication => 'splis-ordinance-publication-panel splis-ordinance-publication-panel--for-publication',
        };
    }

    public function rowClass(): string
    {
        return match ($this) {
            self::Published => 'splis-ordinance-row--published',
            self::ForPublication => 'splis-ordinance-row--for-publication',
        };
    }

    public function iconPath(): string
    {
        return match ($this) {
            self::Published => 'images/ordinances/newspaper.png',
            self::ForPublication => 'images/ordinances/chronometer.png',
        };
    }

    public function showButtonClass(): string
    {
        return match ($this) {
            self::Published => 'splis-btn-primary splis-ordinance-status-btn',
            self::ForPublication => 'splis-btn-secondary splis-btn-ordinance-for-publication splis-ordinance-status-btn',
        };
    }

    public static function fromFillColor(?string $color): ?self
    {
        $normalized = strtoupper(ltrim((string) $color, '#'));

        return match ($normalized) {
            '00FFFF', 'FF00FFFF' => self::Published,
            'FFFF00', 'FFFFFF00' => self::ForPublication,
            default => null,
        };
    }
}
