<?php

namespace App\Enums;

enum ObDocumentSection: string
{
    case CommitteeReport = 'committee_report';
    case Unfinished = 'unfinished';
    case SecondReading = 'second_reading';
    case ThirdReading = 'third_reading';
    case Urgent = 'urgent';
    case RegularUnassigned = 'regular_unassigned';
    case Announcement = 'announcement';

    public function label(): string
    {
        return match ($this) {
            self::CommitteeReport => 'IV. Committee Reports',
            self::Unfinished => 'A. Unfinished Business',
            self::SecondReading => '1. Measures for 2nd Reading',
            self::ThirdReading => '2. Measures for 3rd Reading',
            self::Urgent => '1. Urgent Request/s',
            self::RegularUnassigned => 'Regular Unassigned Business',
            self::Announcement => 'VII. Announcements / Correspondence',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
