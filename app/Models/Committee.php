<?php

namespace App\Models;

use App\Enums\CommitteeMembershipRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Committee extends Model
{
    protected $fillable = [
        'sort_order',
        'name',
        'chair',
        'email',
        'vice_chair',
        'members',
        'secretary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CommitteeTermSecretary, $this>
     */
    public function termSecretaries(): HasMany
    {
        return $this->hasMany(CommitteeTermSecretary::class);
    }

    /**
     * @return HasMany<CommitteeMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CommitteeMembership::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function chairDisplayName(?int $termId = null): string
    {
        return $this->roleDisplayName(CommitteeMembershipRole::Chair, $termId) ?: trim((string) ($this->chair ?? ''));
    }

    public function viceChairDisplayName(?int $termId = null): string
    {
        return $this->roleDisplayName(CommitteeMembershipRole::ViceChair, $termId) ?: trim((string) ($this->vice_chair ?? ''));
    }

    public function secretaryDisplayName(?int $termId = null): string
    {
        $termId ??= CommitteeTerm::query()->current()->value('id');

        if ($termId !== null) {
            $name = $this->termSecretaries()
                ->where('committee_term_id', $termId)
                ->value('name');

            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return trim((string) ($this->secretary ?? ''));
    }

    /**
     * @return list<string>
     */
    public function memberDisplayNames(?int $termId = null): array
    {
        $termId ??= CommitteeTerm::query()->current()->value('id');

        if ($termId === null) {
            return $this->legacyMemberNames();
        }

        $names = $this->memberships()
            ->where('committee_term_id', $termId)
            ->where('role', CommitteeMembershipRole::Member)
            ->orderBy('sort_order')
            ->with('boardMember')
            ->get()
            ->map(fn (CommitteeMembership $membership) => $membership->boardMember?->officialName() ?? '')
            ->filter()
            ->values()
            ->all();

        return $names !== [] ? $names : $this->legacyMemberNames();
    }

    public function roleDisplayName(CommitteeMembershipRole $role, ?int $termId = null): string
    {
        $termId ??= CommitteeTerm::query()->current()->value('id');

        if ($termId === null) {
            return '';
        }

        $membership = $this->memberships()
            ->where('committee_term_id', $termId)
            ->where('role', $role)
            ->with('boardMember')
            ->orderBy('sort_order')
            ->first();

        return $membership?->boardMember?->officialName() ?? '';
    }

    /**
     * @return list<string>
     */
    protected function legacyMemberNames(): array
    {
        $raw = trim((string) ($this->members ?? ''));

        if ($raw === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n|,/', $raw) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
