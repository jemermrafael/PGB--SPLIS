<?php

namespace App\Enums;

enum UserRole: string
{
    case Guest = 'guest';
    case Encoder = 'encoder';
    case EncoderDelete = 'encoder_delete';
    case Admin = 'admin';
    case Superadmin = 'superadmin';
    case BoardMember = 'board_member';
    case MunicipalViewer = 'municipal_viewer';

    public static function fromLegacy(string $code): self
    {
        return match (strtoupper(trim($code))) {
            'G' => self::Guest,
            'E' => self::Encoder,
            'D' => self::EncoderDelete,
            'A' => self::Admin,
            'S' => self::Superadmin,
            'B' => self::BoardMember,
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
            self::BoardMember => 'Board Member',
            self::MunicipalViewer => 'Municipal SB Viewer',
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

    public function canManageUsers(): bool
    {
        return $this === self::Superadmin;
    }

    public function isBoardMember(): bool
    {
        return $this === self::BoardMember;
    }

    public function isMunicipalViewer(): bool
    {
        return $this === self::MunicipalViewer;
    }

    public function canRecordAttendance(): bool
    {
        return in_array($this, [self::Admin, self::Superadmin], true);
    }

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [
            self::Guest,
            self::Encoder,
            self::EncoderDelete,
            self::Admin,
            self::Superadmin,
            self::BoardMember,
            self::MunicipalViewer,
        ];
    }
}
