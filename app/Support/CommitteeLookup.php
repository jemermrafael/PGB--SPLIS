<?php

namespace App\Support;

use App\Models\Committee;

class CommitteeLookup
{
    public static function findById(?int $id): ?Committee
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        return Committee::query()->find($id);
    }

    public static function findByName(?string $name): ?Committee
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $committee = Committee::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($committee !== null) {
            return $committee;
        }

        $normalized = self::normalizeReferralName($name);

        if ($normalized !== $name) {
            $committee = Committee::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized)])
                ->first();

            if ($committee !== null) {
                return $committee;
            }
        }

        return Committee::query()
            ->ordered()
            ->get()
            ->first(fn (Committee $candidate) => self::namesMatch($candidate->name, $name));
    }

    public static function chairFor(?int $committeeId, ?string $committeeName = null): string
    {
        $committee = self::findById($committeeId) ?? self::findByName($committeeName);
        $chair = trim((string) ($committee?->chair ?? ''));

        return $chair;
    }

    public static function normalizeReferralName(string $name): string
    {
        $name = trim($name);

        if (preg_match('/^sp\s+committee\s+on\s+(.+)$/iu', $name, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^committee\s+on\s+(.+)$/iu', $name, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^sp\s+committee\s+(.+)$/iu', $name, $matches)) {
            return trim($matches[1]);
        }

        return $name;
    }

    protected static function namesMatch(string $committeeName, string $referral): bool
    {
        $left = mb_strtolower(trim($committeeName));
        $right = mb_strtolower(self::normalizeReferralName($referral));

        return $left === $right
            || str_contains($right, $left)
            || str_contains($left, $right);
    }
}
