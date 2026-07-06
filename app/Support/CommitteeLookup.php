<?php

namespace App\Support;

use App\Enums\CommitteeMembershipRole;
use App\Models\Committee;
use App\Models\CommitteeTerm;

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

        if ($committee === null) {
            return '';
        }

        return $committee->chairDisplayName();
    }

    public static function obChairFor(?int $committeeId, ?string $committeeName = null): string
    {
        $committee = self::findById($committeeId) ?? self::findByName($committeeName);

        if ($committee === null) {
            return '';
        }

        $termId = CommitteeTerm::query()->current()->value('id');

        if ($termId !== null) {
            $membership = $committee->memberships()
                ->where('committee_term_id', $termId)
                ->where('role', CommitteeMembershipRole::Chair)
                ->with('boardMember')
                ->orderBy('sort_order')
                ->first();

            $name = $membership?->boardMember?->orderOfBusinessName() ?? '';
            if ($name !== '') {
                return $name;
            }
        }

        return self::formatObChairName($committee->chair);
    }

    protected static function formatObChairName(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^board\s+member\s+/i', $raw)) {
            return preg_replace_callback(
                '/^board\s+member\s+/i',
                fn () => 'Board Member ',
                $raw,
            );
        }

        $name = preg_replace('/^(Hon\.|Hon)\s+/i', '', $raw) ?? $raw;

        return 'Board Member '.trim($name);
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
