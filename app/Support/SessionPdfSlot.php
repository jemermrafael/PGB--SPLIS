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
            self::FINAL_JOURNAL,
            self::FINAL_MINUTES,
        ];
    }

    /**
     * Google Drive .docx links only — open externally, never uploaded or mirrored.
     *
     * @return list<string>
     */
    public static function externalLinkOnly(): array
    {
        return [
            self::DRAFT_JOURNAL,
            self::DRAFT_MINUTES,
        ];
    }

    /**
     * Built in the Committee Report Summary maker — no upload or Drive URL.
     *
     * @return list<string>
     */
    public static function makerOnly(): array
    {
        return [
            self::SUMMARY_COMMITTEE_REPORTS,
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

    public static function isExternalLinkOnly(string $slot): bool
    {
        return in_array($slot, self::externalLinkOnly(), true);
    }

    public static function isMakerOnly(string $slot): bool
    {
        return in_array($slot, self::makerOnly(), true);
    }

    /**
     * @return array{field:string,path:?string,upload:?string,filename:?string,label:string,kind:string}
     */
    public static function config(string $slot): array
    {
        return match ($slot) {
            self::SUMMARY_COMMITTEE_REPORTS => [
                'field' => self::SUMMARY_COMMITTEE_REPORTS,
                'path' => null,
                'upload' => null,
                'filename' => null,
                'label' => 'Summary of Comm. Reports',
                'kind' => 'maker',
            ],
            self::COMMITTEE_REPORTS_FOLDER => [
                'field' => self::COMMITTEE_REPORTS_FOLDER,
                'path' => null,
                'upload' => 'committee_report_files',
                'filename' => null,
                'label' => 'Committee Reports',
                'kind' => 'folder',
            ],
            self::DRAFT_JOURNAL => [
                'field' => self::DRAFT_JOURNAL,
                'path' => null,
                'upload' => null,
                'filename' => null,
                'label' => 'Draft Journal',
                'kind' => 'external_link',
            ],
            self::DRAFT_MINUTES => [
                'field' => self::DRAFT_MINUTES,
                'path' => null,
                'upload' => null,
                'filename' => null,
                'label' => 'Draft Minutes',
                'kind' => 'external_link',
            ],
            self::FINAL_JOURNAL => [
                'field' => self::FINAL_JOURNAL,
                'path' => 'pdf_final_journal_path',
                'upload' => 'pdf_final_journal_file',
                'filename' => 'final-journal',
                'label' => 'Final Journal',
                'kind' => 'file',
            ],
            self::FINAL_MINUTES => [
                'field' => self::FINAL_MINUTES,
                'path' => 'pdf_final_minutes_path',
                'upload' => 'pdf_final_minutes_file',
                'filename' => 'final-minutes',
                'label' => 'Final Minutes',
                'kind' => 'file',
            ],
            default => throw new \InvalidArgumentException('Unknown session PDF slot: '.$slot),
        };
    }

    public static function uploadMimes(string $slot): string
    {
        return 'pdf,jpg,jpeg,png,gif,webp';
    }

    public static function uploadAcceptAttribute(string $slot): string
    {
        return 'application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp';
    }
}
