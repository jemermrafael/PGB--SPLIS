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
}
