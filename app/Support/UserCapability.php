<?php

namespace App\Support;

/**
 * Per-user module capabilities for Encoder / Encoder Delete accounts.
 * Null capabilities on a user means "all modules" (legacy default).
 */
class UserCapability
{
    public const AGENDA = 'agenda';

    public const RESOLUTIONS = 'resolutions';

    public const ORDINANCES = 'ordinances';

    public const ORDER_OF_BUSINESS = 'order_of_business';

    public const COMMITTEE_REPORTS = 'committee_reports';

    public const COMMITTEES = 'committees';

    public const REFERENCES = 'references';

    public const DIRECTORY = 'directory';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::AGENDA,
            self::RESOLUTIONS,
            self::ORDINANCES,
            self::ORDER_OF_BUSINESS,
            self::COMMITTEE_REPORTS,
            self::COMMITTEES,
            self::REFERENCES,
            self::DIRECTORY,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::AGENDA => 'Agenda',
            self::RESOLUTIONS => 'Resolutions',
            self::ORDINANCES => 'Ordinances / Appropriation Ordinances',
            self::ORDER_OF_BUSINESS => 'Order of Business (sessions, OB Maker, summary)',
            self::COMMITTEE_REPORTS => 'Committee Reports / Schedule referrals',
            self::COMMITTEES => 'Committees & Board Member roster',
            self::REFERENCES => 'Reference Materials',
            self::DIRECTORY => 'Directory',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            self::AGENDA => 'Create, edit, and trash agenda items.',
            self::RESOLUTIONS => 'Create, edit, and trash resolutions.',
            self::ORDINANCES => 'Manage provincial and appropriation ordinances.',
            self::ORDER_OF_BUSINESS => 'Manage sessions, OB Maker, and committee report summaries.',
            self::COMMITTEE_REPORTS => 'Staff committee reports and scheduled committee referrals.',
            self::COMMITTEES => 'Manage committees, terms, and board member roster.',
            self::REFERENCES => 'Create and manage reference materials.',
            self::DIRECTORY => 'Manage directory entries.',
        ];
    }
}
