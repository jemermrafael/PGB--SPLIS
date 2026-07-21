<?php

namespace App\Support;

class ObTitleMarkup
{
    public static function sanitize(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $html = preg_replace('/<(?:div|p)(?:\s[^>]*)?>/iu', '', $html) ?? $html;
        $html = preg_replace('/<\/(?:div|p)>/iu', '<br>', $html) ?? $html;
        $html = strip_tags($html, '<strong><b><mark><br>');
        $html = preg_replace('/<(?:strong|b)(?:\s[^>]*)?>/iu', '<strong>', $html) ?? $html;
        $html = preg_replace('/<\/(?:strong|b)>/iu', '</strong>', $html) ?? $html;
        $html = preg_replace('/<mark(?:\s[^>]*)?>/iu', '<mark>', $html) ?? $html;
        $html = preg_replace('/<br(?:\s[^>]*)?\/?>/iu', '<br>', $html) ?? $html;
        $html = preg_replace('/(?:<br>){3,}/iu', '<br><br>', $html) ?? $html;
        $html = preg_replace('/^(?:<br>)+|(?:<br>)+$/iu', '', $html) ?? $html;

        return trim($html) !== '' ? trim($html) : null;
    }

    public static function plainText(?string $html): string
    {
        $html = self::sanitize($html) ?? '';
        $text = preg_replace('/<br>/iu', "\n", $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text));
    }

    public static function forTitle(?string $html, ?string $title): ?string
    {
        $sanitized = self::sanitize($html);

        if ($sanitized === null) {
            return null;
        }

        return self::normalizedText(self::plainText($sanitized)) === self::normalizedText((string) $title)
            ? $sanitized
            : null;
    }

    private static function normalizedText(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    }
}
