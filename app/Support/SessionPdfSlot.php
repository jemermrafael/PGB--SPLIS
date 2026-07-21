<?php

namespace App\Support;

final class SessionPdfSlot
{
    public const SUMMARY_COMMITTEE_REPORTS = 'pdf_summary_committee_reports';

    public const COMMITTEE_REPORTS_FOLDER = 'pdf_committee_reports';

    public const DRAFT_JOURNAL = 'pdf_draft_journal';

    public const DRAFT_MINUTES = 'pdf_draft_minutes';

    public const FINAL_JOURNAL = 'pdf_final_journal';

    public const FINAL_MINUTES = 'pdf_final_minutes';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::SUMMARY_COMMITTEE_REPORTS,
            self::COMMITTEE_REPORTS_FOLDER,
            self::DRAFT_JOURNAL,
            self::DRAFT_MINUTES,
            self::FINAL_JOURNAL,
            self::FINAL_MINUTES,
        ];
    }

    /**
     * @return list<string>
     */
    public static function mirrorable(): array
    {
        return [
            self::SUMMARY_COMMITTEE_REPORTS,
            self::DRAFT_JOURNAL,
            self::DRAFT_MINUTES,
            self::FINAL_JOURNAL,
            self::FINAL_MINUTES,
        ];
    }

    public static function isValid(?string $slot): bool
    {
        return in_array($slot, self::all(), true);
    }

    public static function isMirrorable(string $slot): bool
    {
        return in_array($slot, self::mirrorable(), true);
    }

    /**
     * @return array{field:string,path:?string,upload:?string,filename:?string,label:string,kind:string,accepts_office:bool}
     */
    public static function config(string $slot): array
    {
        return match ($slot) {
            self::SUMMARY_COMMITTEE_REPORTS => [
                'field' => self::SUMMARY_COMMITTEE_REPORTS,
                'path' => 'pdf_summary_committee_reports_path',
                'upload' => 'pdf_summary_committee_reports_file',
                'filename' => 'summary-committee-reports',
                'label' => 'Summary of Comm. Reports',
                'kind' => 'file',
                'accepts_office' => false,
            ],
            self::COMMITTEE_REPORTS_FOLDER => [
                'field' => self::COMMITTEE_REPORTS_FOLDER,
                'path' => null,
                'upload' => 'committee_report_files',
                'filename' => null,
                'label' => 'Committee Reports',
                'kind' => 'folder',
                'accepts_office' => false,
            ],
            self::DRAFT_JOURNAL => [
                'field' => self::DRAFT_JOURNAL,
                'path' => 'pdf_draft_journal_path',
                'upload' => 'pdf_draft_journal_file',
                'filename' => 'draft-journal',
                'label' => 'Draft Journal',
                'kind' => 'file',
                'accepts_office' => true,
            ],
            self::DRAFT_MINUTES => [
                'field' => self::DRAFT_MINUTES,
                'path' => 'pdf_draft_minutes_path',
                'upload' => 'pdf_draft_minutes_file',
                'filename' => 'draft-minutes',
                'label' => 'Draft Minutes',
                'kind' => 'file',
                'accepts_office' => true,
            ],
            self::FINAL_JOURNAL => [
                'field' => self::FINAL_JOURNAL,
                'path' => 'pdf_final_journal_path',
                'upload' => 'pdf_final_journal_file',
                'filename' => 'final-journal',
                'label' => 'Final Journal',
                'kind' => 'file',
                'accepts_office' => false,
            ],
            self::FINAL_MINUTES => [
                'field' => self::FINAL_MINUTES,
                'path' => 'pdf_final_minutes_path',
                'upload' => 'pdf_final_minutes_file',
                'filename' => 'final-minutes',
                'label' => 'Final Minutes',
                'kind' => 'file',
                'accepts_office' => false,
            ],
            default => throw new \InvalidArgumentException('Unknown session PDF slot: '.$slot),
        };
    }

    public static function acceptsOfficeDocuments(string $slot): bool
    {
        return self::config($slot)['accepts_office'] === true;
    }

    public static function uploadMimes(string $slot): string
    {
        $base = 'pdf,jpg,jpeg,png,gif,webp';

        return self::acceptsOfficeDocuments($slot) ? $base.',doc,docx' : $base;
    }

    public static function uploadAcceptAttribute(string $slot): string
    {
        $base = 'application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp';

        if (! self::acceptsOfficeDocuments($slot)) {
            return $base;
        }

        return $base.',application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,.doc,.docx';
    }
}
