<?php

namespace App\Support;

class ObCommitteeFormatter
{
    public static function spCommitteeLabel(?string $committee): string
    {
        $committee = trim((string) $committee);
        if ($committee === '') {
            return '';
        }

        if (preg_match('/^sp\s+committee/i', $committee)) {
            return mb_strtoupper($committee);
        }

        if (preg_match('/^committee\s+on\s+/i', $committee)) {
            return 'SP '.mb_strtoupper($committee);
        }

        return 'SP COMMITTEE ON '.mb_strtoupper($committee);
    }

    public static function spCommitteeReportLabel(?string $committee): string
    {
        $committee = trim((string) $committee);
        if ($committee === '') {
            return '';
        }

        if (preg_match('/^sp\s+committee\s+on\s+/i', $committee)) {
            return preg_replace_callback('/^sp\s+committee\s+on\s+/i', fn () => 'SP Committee on ', $committee);
        }

        if (preg_match('/^committee\s+on\s+/i', $committee)) {
            return 'SP Committee on '.mb_substr($committee, 13);
        }

        return 'SP Committee on '.$committee;
    }

    public static function chairLine(?string $chair): string
    {
        $chair = trim((string) $chair);
        if ($chair === '') {
            return 'Chair:';
        }

        if (stripos($chair, 'chair') === 0) {
            return $chair;
        }

        return 'Chair: '.$chair;
    }

    public static function chairedByLine(?string $chair): string
    {
        $chair = trim((string) $chair);
        if ($chair === '') {
            return 'Chaired by:';
        }

        if (stripos($chair, 'chaired by') === 0) {
            return $chair;
        }

        return 'Chaired by: '.$chair;
    }
}
