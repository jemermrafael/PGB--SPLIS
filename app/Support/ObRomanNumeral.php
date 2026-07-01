<?php

namespace App\Support;

class ObRomanNumeral
{
    public static function normalize(?string $numeral): string
    {
        return rtrim(trim((string) $numeral), '.');
    }

    /**
     * Display form with a trailing period (e.g. I., II., III.).
     */
    public static function display(?string $numeral): string
    {
        $normalized = self::normalize($numeral);

        if ($normalized === '') {
            return '';
        }

        return $normalized.'.';
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function formatSectionContent(array $content): array
    {
        if (isset($content['numeral'])) {
            $content['numeral'] = self::display($content['numeral']);
        }

        return $content;
    }
}
