<?php

namespace App\Support;

use Illuminate\Support\Str;

class Permalink
{
    public static function yearAndId(int $year, int|string $id): string
    {
        return $year.'-'.$id;
    }

    public static function agendaKey(int $year, ?string $trackingNo, int|string $id): string
    {
        $trackingNo = trim((string) $trackingNo);

        if ($trackingNo !== '') {
            return $year.'-'.$trackingNo;
        }

        return $year.'-U-'.$id;
    }

    /**
     * @return array{year: int, tracking_no: string}|array{year: int, unnumbered_id: int}|null
     */
    public static function parseAgendaKey(string $value): ?array
    {
        $value = trim($value);

        if (preg_match('/^(\d{4})-U-(\d+)$/i', $value, $matches) === 1) {
            return [
                'year' => (int) $matches[1],
                'unnumbered_id' => (int) $matches[2],
            ];
        }

        if (preg_match('/^(\d{4})-(.+)$/', $value, $matches) === 1) {
            return [
                'year' => (int) $matches[1],
                'tracking_no' => $matches[2],
            ];
        }

        return null;
    }

    public static function parseYearAndId(string $value): ?array
    {
        if (preg_match('/^(\d{4})-(\d+)$/', trim($value), $matches) !== 1) {
            return null;
        }

        return [
            'year' => (int) $matches[1],
            'id' => (int) $matches[2],
        ];
    }

    /**
     * Split a duplicate-resolution permalink such as "2005-A-0071-12345"
     * into resolution_no + legacy_sp_id when the trailing segment is numeric.
     *
     * @return array{resolution_no: string, legacy_sp_id: int}|null
     */
    public static function parseResolutionDuplicateKey(string $value): ?array
    {
        $value = trim($value);

        if (preg_match('/^(.+)-(\d+)$/', $value, $matches) !== 1) {
            return null;
        }

        return [
            'resolution_no' => $matches[1],
            'legacy_sp_id' => (int) $matches[2],
        ];
    }

    public static function resolutionDuplicateKey(string $resolutionNo, int|string $legacySpId): string
    {
        return trim($resolutionNo).'-'.$legacySpId;
    }

    public static function yearAndNumber(int $year, int|string $number): string
    {
        return $year.'-'.$number;
    }

    public static function parseYearAndNumber(string $value): ?array
    {
        if (preg_match('/^(\d{4})-(\d+)$/', trim($value), $matches) !== 1) {
            return null;
        }

        return [
            'year' => (int) $matches[1],
            'number' => (int) $matches[2],
        ];
    }

    public static function yearAndOrdinal(int $year, string $ordinal): string
    {
        return $year.'-'.Str::lower($ordinal);
    }

    public static function parseYearAndOrdinal(string $value): ?array
    {
        if (preg_match('/^(\d{4})-(\d+(?:st|nd|rd|th))$/i', trim($value), $matches) !== 1) {
            return null;
        }

        return [
            'year' => (int) $matches[1],
            'ordinal' => Str::lower($matches[2]),
            'number' => (int) $matches[2],
        ];
    }

    public static function ordinalFromSessionNumber(?string $sessionNumber): ?string
    {
        $sessionNumber = trim((string) $sessionNumber);

        if ($sessionNumber === '') {
            return null;
        }

        if (preg_match('/(\d+)\s*(st|nd|rd|th)\b/i', $sessionNumber, $matches) === 1) {
            return Str::lower($matches[1].$matches[2]);
        }

        if (ctype_digit($sessionNumber)) {
            return self::ordinalSuffix((int) $sessionNumber);
        }

        return null;
    }

    public static function ordinalSuffix(int $number): string
    {
        $mod100 = $number % 100;

        if ($mod100 >= 11 && $mod100 <= 13) {
            return $number.'th';
        }

        return $number.match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    public static function boardMemberSlug(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'member';
    }

    public static function isLegacyNumericId(string $value): bool
    {
        return ctype_digit(trim($value));
    }
}
