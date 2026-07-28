<?php

namespace App\Support;

final class EmailHtml
{
    /**
     * Allowed tags for email notification bodies.
     */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><span><div><h1><h2><h3><h4><ul><ol><li><a><img><table><thead><tbody><tr><th><td><hr><blockquote><pre><code>';

    public static function containsMarkup(string $value): bool
    {
        return (bool) preg_match(
            '/<\s*\/?\s*(?:p|br|strong|b|em|i|u|s|span|div|h[1-4]|ul|ol|li|a|img|table|thead|tbody|tr|th|td|hr|blockquote|pre|code)\b/i',
            $value,
        );
    }

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Drop event handlers / javascript: URLs from attributes.
        $html = preg_replace_callback(
            '/<\s*([a-z0-9]+)([^>]*)>/i',
            function (array $matches): string {
                $tag = strtolower($matches[1]);
                $attrs = self::sanitizeAttributes($tag, $matches[2] ?? '');

                return '<'.$tag.$attrs.'>';
            },
            $html,
        ) ?? $html;

        return $html;
    }

    /**
     * Format a rendered template body for email HTML output.
     */
    public static function toEmailHtml(string $body): string
    {
        if (! self::containsMarkup($body)) {
            return nl2br(e($body), false);
        }

        return self::sanitize($body);
    }

    public static function plainSubject(string $subject): string
    {
        return trim(html_entity_decode(strip_tags($subject), ENT_QUOTES | ENT_HTML5));
    }

    protected static function sanitizeAttributes(string $tag, string $attributeString): string
    {
        if (trim($attributeString) === '') {
            return '';
        }

        $allowed = match ($tag) {
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'style'],
            'td', 'th' => ['colspan', 'rowspan', 'align', 'style', 'width'],
            'table' => ['border', 'cellpadding', 'cellspacing', 'width', 'style', 'role'],
            'p', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'td', 'th', 'li' => ['style', 'align'],
            default => ['style'],
        };

        $safe = [];
        if (preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(["\'])(.*?)\2/s', $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                $value = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5);

                if (! in_array($name, $allowed, true)) {
                    continue;
                }

                if (str_starts_with($name, 'on')) {
                    continue;
                }

                if ($name === 'href' || $name === 'src') {
                    $value = trim($value);
                    if (! self::isSafeUrl($value, allowDataImage: $name === 'src')) {
                        continue;
                    }
                }

                if ($name === 'target') {
                    $value = '_blank';
                    $safe['rel'] = 'noopener noreferrer';
                }

                if ($name === 'style') {
                    $value = self::sanitizeStyle($value);
                    if ($value === '') {
                        continue;
                    }
                }

                $safe[$name] = $value;
            }
        }

        if ($safe === []) {
            return '';
        }

        $parts = [];
        foreach ($safe as $name => $value) {
            $parts[] = $name.'="'.e($value).'"';
        }

        return ' '.implode(' ', $parts);
    }

    protected static function isSafeUrl(string $url, bool $allowDataImage = false): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        if ($allowDataImage && preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $url) === 1) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    protected static function sanitizeStyle(string $style): string
    {
        $allowedProps = [
            'color',
            'background-color',
            'font-size',
            'font-weight',
            'font-style',
            'text-align',
            'text-decoration',
            'line-height',
            'margin',
            'margin-top',
            'margin-right',
            'margin-bottom',
            'margin-left',
            'padding',
            'padding-top',
            'padding-right',
            'padding-bottom',
            'padding-left',
            'border',
            'border-radius',
            'width',
            'max-width',
            'height',
            'max-height',
            'display',
        ];

        $parts = [];
        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$prop, $value] = array_map('trim', explode(':', $declaration, 2));
            $prop = strtolower($prop);

            if (! in_array($prop, $allowedProps, true)) {
                continue;
            }

            if ($value === '' || preg_match('/expression|javascript|import|behavior|url\s*\(\s*[\'"]?\s*javascript/i', $value)) {
                continue;
            }

            $parts[] = $prop.': '.$value;
        }

        return implode('; ', $parts);
    }
}
