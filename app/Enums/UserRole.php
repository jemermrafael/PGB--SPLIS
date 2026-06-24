<?php

namespace App\Enums;

enum UserRole: string
{
    case Guest = 'guest';
    case Encoder = 'encoder';
    case EncoderDelete = 'encoder_delete';
    case Admin = 'admin';
    case Superadmin = 'superadmin';

    public static function fromLegacy(string $code): self
    {
        return match (strtoupper(trim($code))) {
            'G' => self::Guest,
            'E' => self::Encoder,
            'D' => self::EncoderDelete,
            'A' => self::Admin,
            'S' => self::Superadmin,
            default => self::Guest,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Guest => 'Guest',
            self::Encoder => 'Encoder',
            self::EncoderDelete => 'Encoder with Delete',
            self::Admin => 'Admin',
            self::Superadmin => 'Superadmin',
        };
    }

    public function canCreate(): bool
    {
        return in_array($this, [self::Encoder, self::EncoderDelete, self::Admin, self::Superadmin], true);
    }

    public function canDelete(): bool
    {
        return in_array($this, [self::EncoderDelete, self::Admin, self::Superadmin], true);
    }

    public function canAdmin(): bool
    {
        return in_array($this, [self::Admin, self::Superadmin], true);
    }
}
