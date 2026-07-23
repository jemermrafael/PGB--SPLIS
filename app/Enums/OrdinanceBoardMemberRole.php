<?php

namespace App\Enums;

enum OrdinanceBoardMemberRole: string
{
    case Author = 'author';
    case Sponsor = 'sponsor';
    case AuthoredSponsored = 'authored_sponsored';

    public function label(): string
    {
        return match ($this) {
            self::Author => 'Authored',
            self::Sponsor => 'Sponsored',
            self::AuthoredSponsored => 'Authored and Sponsored',
        };
    }

    public function formFieldName(): string
    {
        return match ($this) {
            self::Author => 'author_member_ids',
            self::Sponsor => 'sponsor_member_ids',
            self::AuthoredSponsored => 'authored_sponsored_member_ids',
        };
    }
}
