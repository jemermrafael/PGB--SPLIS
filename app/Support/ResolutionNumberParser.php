<?php

namespace App\Support;

class ResolutionNumberParser
{
    /**
     * @return array{kind: string, sequence: ?int, raw: string}
     */
    public static function parseSpResNo(?string $spResNo): array
    {
        $raw = trim((string) $spResNo);
        if ($raw === '') {
            return ['kind' => 'unknown', 'sequence' => null, 'raw' => ''];
        }

        if (preg_match('/^(?:ord\.?\s*no\.?|ao\s*no\.?)\s*([\w-]+)/i', $raw, $m)) {
            $seq = self::digitsToInt($m[1]);

            return ['kind' => 'ordinance', 'sequence' => $seq, 'raw' => $raw];
        }

        if (preg_match('/^\d+$/', $raw)) {
            return ['kind' => 'resolution', 'sequence' => (int) $raw, 'raw' => $raw];
        }

        if (preg_match('/(\d+)\s*$/', $raw, $m)) {
            return ['kind' => 'unknown', 'sequence' => (int) $m[1], 'raw' => $raw];
        }

        return ['kind' => 'unknown', 'sequence' => null, 'raw' => $raw];
    }

    public static function extractSequence(?string $resolutionNo): ?int
    {
        $resolutionNo = trim((string) $resolutionNo);
        if ($resolutionNo === '') {
            return null;
        }

        if (preg_match('/^\d{4}-[A-Z]-(\d+)$/i', $resolutionNo, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^\d+$/', $resolutionNo)) {
            return (int) $resolutionNo;
        }

        if (preg_match('/-(\d+)$/', $resolutionNo, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    public static function buildOfficialNumber(int $series, int $sequence, string $type = 'B'): string
    {
        return sprintf('%d-%s-%04d', $series, strtoupper($type), $sequence);
    }

    public static function normalizeTitle(?string $title): string
    {
        $title = strtoupper(trim((string) $title));
        $title = preg_replace('/[^A-Z0-9\s]/', ' ', $title) ?? $title;
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;

        return trim($title);
    }

    public static function titleSimilarity(?string $a, ?string $b): float
    {
        $a = self::normalizeTitle($a);
        $b = self::normalizeTitle($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        $aShort = substr($a, 0, 200);
        $bShort = substr($b, 0, 200);

        if (str_contains($aShort, $bShort) || str_contains($bShort, $aShort)) {
            return 95.0;
        }

        $aWords = array_filter(explode(' ', $aShort));
        $bWords = array_flip(array_filter(explode(' ', $bShort)));
        if ($aWords !== [] && $bWords !== []) {
            $overlap = 0;
            foreach ($aWords as $word) {
                if (strlen($word) > 3 && isset($bWords[$word])) {
                    $overlap++;
                }
            }
            $ratio = $overlap / max(count($aWords), 1);
            if ($ratio >= 0.4) {
                return round(60 + ($ratio * 35), 1);
            }
        }

        return round(min(55, ($overlap / max(count($bWords), 1)) * 100), 1);
    }

    protected static function digitsToInt(string $value): ?int
    {
        if (preg_match('/(\d+)/', $value, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
