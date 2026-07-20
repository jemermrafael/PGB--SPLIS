<?php

namespace App\Support;

final class AgendaPdfSlot
{
    public const REQUEST = 'request';

    public const COMMITTEE_REPORT = 'committee_report';

    public const RESO_ORD_AO = 'reso_ord_ao';

    public const JOURNAL = 'journal';

    public const MINUTES = 'minutes';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::REQUEST,
            self::COMMITTEE_REPORT,
            self::RESO_ORD_AO,
            self::JOURNAL,
            self::MINUTES,
        ];
    }

    public static function isValid(?string $slot): bool
    {
        return in_array($slot, self::all(), true);
    }

    /**
     * @return array{url: string, path: string, upload: string, filename: string, label: string}
     */
    public static function config(string $slot): array
    {
        return match ($slot) {
            self::REQUEST => [
                'url' => 'request_pdf_url',
                'path' => 'request_pdf_path',
                'upload' => 'request_pdf',
                'filename' => 'request',
                'label' => 'Request PDF',
            ],
            self::COMMITTEE_REPORT => [
                'url' => 'committee_report_url',
                'path' => 'committee_report_pdf_path',
                'upload' => 'committee_report_pdf',
                'filename' => 'committee-report',
                'label' => 'Committee report',
            ],
            self::RESO_ORD_AO => [
                'url' => 'reso_ord_ao_url',
                'path' => 'reso_ord_ao_pdf_path',
                'upload' => 'reso_ord_ao_pdf',
                'filename' => 'output',
                'label' => 'Output PDF',
            ],
            self::JOURNAL => [
                'url' => 'journal_url',
                'path' => 'journal_pdf_path',
                'upload' => 'journal_pdf',
                'filename' => 'journal',
                'label' => 'Journal of proceedings',
            ],
            self::MINUTES => [
                'url' => 'minutes_url',
                'path' => 'minutes_pdf_path',
                'upload' => 'minutes_pdf',
                'filename' => 'minutes',
                'label' => 'Minutes of session',
            ],
            default => throw new \InvalidArgumentException('Unknown agenda PDF slot: '.$slot),
        };
    }
}
