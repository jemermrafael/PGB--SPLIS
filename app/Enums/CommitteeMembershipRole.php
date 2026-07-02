<?php

namespace App\Enums;

enum CommitteeMembershipRole: string
{
    case Chair = 'chair';
    case ViceChair = 'vice_chair';
    case Member = 'member';
    case Secretary = 'secretary';

    public function label(): string
    {
        return match ($this) {
            self::Chair => 'Chair',
            self::ViceChair => 'Vice chair',
            self::Member => 'Member',
            self::Secretary => 'Secretary',
        };
    }

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [
            self::Chair,
            self::ViceChair,
            self::Member,
            self::Secretary,
        ];
    }
}
