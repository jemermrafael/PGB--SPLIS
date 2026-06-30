<?php

namespace App\Support;

use Carbon\Carbon;

class SptrackRecordDatetime
{
    /**
     * Parse sptrack RecAdded / RecModified values.
     *
     * CSV examples: "7/26/2017 9:50:45 AM May", "11/21/2017 3:30:00 PM May"
     * The trailing word is a legacy username and is discarded.
     */
    public static function parse(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = self::extractDatetimePortion($value);

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Legacy username after the AM/PM token, if present.
     */
    public static function extractUsername(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match(
            '/^\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}(?::\d{2})?\s*[AP]M\s+(.+)$/i',
            $value,
            $matches
        )) {
            $username = trim($matches[1]);

            return $username !== '' ? $username : null;
        }

        return null;
    }

    protected static function extractDatetimePortion(string $value): string
    {
        if (preg_match(
            '/^(\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}(?::\d{2})?\s*[AP]M)/i',
            $value,
            $matches
        )) {
            return $matches[1];
        }

        return $value;
    }
}
