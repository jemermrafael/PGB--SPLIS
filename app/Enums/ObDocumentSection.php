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
            self::CommitteeReport => 'IV. Committee reports',
            self::Unfinished => 'A. Unfinished business',
            self::SecondReading => '1. Measures for 2nd reading',
            self::ThirdReading => '2. Measures for 3rd reading',
            self::Urgent => '1. Urgent request/s',
            self::RegularUnassigned => 'Regular unassigned business',
            self::Announcement => 'VII. Announcements / correspondence',
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
