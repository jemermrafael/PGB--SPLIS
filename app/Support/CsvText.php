<?php

namespace App\Support;

final class CsvText
{
    /**
     * Legacy exports arrive as Windows-1252 (curly quotes, en dashes), which MySQL
     * rejects on utf8mb4 columns with "Incorrect string value". Convert anything
     * that is not already valid UTF-8.
     */
    public static function toUtf8(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $value = self::stripBom($value);

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    protected static function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF")
            ? substr($value, 3)
            : $value;
    }
}
