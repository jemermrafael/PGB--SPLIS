<?php

namespace App\Support;

class OrdinanceNumberParser
{
    public static function parse(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $number = (int) $value;

            return $number > 0 ? $number : null;
        }

        if (preg_match('/ord\.?\s*no\.?\s*#?\s*(\d+)/i', $value, $matches) === 1) {
            $number = (int) $matches[1];

            return $number > 0 ? $number : null;
        }

        if (preg_match('/\bno\.?\s*#?\s*(\d+)/i', $value, $matches) === 1) {
            $number = (int) $matches[1];

            return $number > 0 ? $number : null;
        }

        return null;
    }

    public static function isDisplayFormat(?string $value): bool
    {
        $value = trim((string) $value);

        return preg_match('/ord\.?\s*no\.?/i', $value) === 1
            || preg_match('/\bno\.?\s*#?\s*\d+/i', $value) === 1;
    }

    /**
     * True when the value clearly refers to an appropriation ordinance
     * (e.g. "AO No. 1", "Appro. Ord. No. 51", "App. Ord. 3").
     */
    public static function looksLikeAppropriationOrdinanceReference(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        return preg_match('/\bappro(?:priation)?\.?\s*ord\.?\b/i', $value) === 1
            || preg_match('/\bapp\.?\s*ord\.?\b/i', $value) === 1
            || preg_match('/\ba\.?\s*o\.?\b/i', $value) === 1;
    }

    /**
     * True when the value clearly refers to an ordinance (e.g. "Ord. No. 22", "Ord 5").
     */
    public static function looksLikeOrdinanceReference(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if (self::looksLikeAppropriationOrdinanceReference($value)) {
            return false;
        }

        return preg_match('/\bord\.?\b/i', $value) === 1;
    }
}
