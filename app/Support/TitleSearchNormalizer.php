<?php

namespace App\Support;

class TitleSearchNormalizer
{
    /** @var list<string> */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'of', 'for', 'and', 'to', 'in', 'on', 'at', 'by', 'or', 'as', 'is',
        'be', 'with', 'from', 'this', 'that', 'its', 'are', 'was', 'were', 'been', 'being',
        'have', 'has', 'had', 'not', 'but', 'into', 'upon', 'under', 'over', 'such', 'than',
        'act', 'resolution', 'ordinance', 'approving', 'authorizing', 'requesting',
    ];

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[\p{P}\p{S}"]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<string>
     */
    public static function significantTokens(string $text, int $minLength = 4): array
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $unique = [];

        foreach ($tokens as $token) {
            if (mb_strlen($token) < $minLength) {
                continue;
            }

            if (in_array($token, self::STOP_WORDS, true)) {
                continue;
            }

            $unique[$token] = $token;
        }

        $result = array_values($unique);
        usort($result, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function primaryTokens(string $text, int $limit = 4): array
    {
        return array_slice(self::significantTokens($text), 0, $limit);
    }
}
