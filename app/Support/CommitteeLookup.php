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

    /**
     * @return list<string>
     */
    public static function alternatePhraseVariants(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $normalized = self::normalizeReferralName($name);
        $variants = [$name, $normalized];

        foreach ([$name, $normalized] as $candidate) {
            $variants[] = str_replace(' & ', ' and ', str_replace('&', ' and ', $candidate));
            $variants[] = preg_replace('/\band\b/i', '&', $candidate) ?? $candidate;
        }

        $short = trim((string) preg_replace('/,.*/', '', $normalized));
        if ($short !== '') {
            $variants[] = $short;
        }

        foreach (explode(',', $normalized) as $segment) {
            $segment = trim($segment);
            if ($segment !== '') {
                $variants[] = $segment;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * @return list<string>
     */
    public static function matchPatterns(string $committeeName): array
    {
        $patterns = self::alternatePhraseVariants($committeeName);

        foreach (self::alternatePhraseVariants($committeeName) as $variant) {
            $patterns[] = mb_strtoupper($variant);
            $firstWord = trim((string) preg_split('/\s+/', $variant)[0]);
            if (self::isUsableMatchPattern($firstWord)) {
                $patterns[] = $firstWord;
                $patterns[] = mb_strtoupper($firstWord);
            }
        }

        return array_values(array_unique(array_filter(
            $patterns,
            fn (string $pattern): bool => self::isUsableMatchPattern($pattern),
        )));
    }

    protected static function isUsableMatchPattern(string $pattern): bool
    {
        $pattern = trim($pattern);
        if (mb_strlen($pattern) < 3) {
            return false;
        }

        return ! in_array(mb_strtolower($pattern), [
            'and',
            'the',
            'for',
            'ors',
            'sp',
        ], true);
    }

    public static function referralMatchesCommittee(?string $referral, string $committeeName): bool
    {
        $referral = trim((string) $referral);
        if ($referral === '') {
            return false;
        }

        $referralLower = mb_strtolower(self::normalizeReferralName($referral));
        $committeeLower = mb_strtolower(self::normalizeReferralName($committeeName));

        if ($referralLower === $committeeLower) {
            return true;
        }

        if (str_contains($committeeLower, $referralLower) || str_contains($referralLower, $committeeLower)) {
            return true;
        }

        $referralAnd = str_replace(' & ', ' and ', str_replace('&', ' and ', $referralLower));
        $committeeAnd = str_replace(' & ', ' and ', str_replace('&', ' and ', $committeeLower));

        if ($referralAnd === $committeeAnd) {
            return true;
        }

        if (str_contains($committeeAnd, $referralAnd) || str_contains($referralAnd, $committeeAnd)) {
            return true;
        }

        foreach (self::matchPatterns($committeeName) as $pattern) {
            if ($pattern !== '' && str_contains(mb_strtolower($referral), mb_strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    public static function applyAgendaCommitteeFilter(\Illuminate\Database\Eloquent\Builder $query, Committee $committee, string $column = 'committee_referred'): void
    {
        $patterns = self::matchPatterns($committee->name);

        $query->where(function (\Illuminate\Database\Eloquent\Builder $builder) use ($patterns, $column, $committee): void {
            foreach ($patterns as $pattern) {
                $builder->orWhere($column, 'like', '%'.$pattern.'%');
            }

            $builder->orWhereRaw(
                'LENGTH(TRIM('.$column.')) >= 3 AND LOWER(?) LIKE CONCAT(\'%\', LOWER(TRIM('.$column.')), \'%\')',
                [$committee->name],
            );
        });
    }

    public static function applyResolutionCommitteeFilter(\Illuminate\Database\Eloquent\Builder $query, Committee $committee, string $column = 'committee'): void
    {
        $patterns = self::matchPatterns($committee->name);

        $query->where(function (\Illuminate\Database\Eloquent\Builder $builder) use ($patterns, $column, $committee): void {
            foreach ($patterns as $pattern) {
                $builder->orWhere($column, 'like', '%'.$pattern.'%');
            }

            $builder->orWhereRaw(
                'LENGTH(TRIM('.$column.')) >= 3 AND LOWER(?) LIKE CONCAT(\'%\', LOWER(TRIM('.$column.')), \'%\')',
                [$committee->name],
            );
        });
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
