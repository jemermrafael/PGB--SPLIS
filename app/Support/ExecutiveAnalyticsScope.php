<?php

namespace App\Support;

use App\Models\Committee;
use Illuminate\Support\Collection;

class ExecutiveAnalyticsScope
{
    /**
     * @param  Collection<int, Committee>|null  $committees
     */
    public function __construct(
        public readonly bool $fullAccess,
        public readonly ?Collection $committees = null,
        public readonly ?int $boardMemberId = null,
        public readonly ?string $boardMemberName = null,
    ) {}

    public static function full(): self
    {
        return new self(fullAccess: true);
    }

    public static function empty(): self
    {
        return new self(fullAccess: false, committees: collect());
    }

    public function isFull(): bool
    {
        return $this->fullAccess;
    }

    /**
     * @return list<int>
     */
    public function committeeIds(): array
    {
        return ($this->committees ?? collect())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function allowsCommittee(?int $committeeId): bool
    {
        if ($this->isFull() || $committeeId === null) {
            return true;
        }

        return in_array($committeeId, $this->committeeIds(), true);
    }
}
