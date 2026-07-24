<?php

namespace App\Models;

use App\Enums\CommitteeMembershipRole;
use App\Models\Concerns\NavigatesById;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Committee extends Model
{
    use NavigatesById;
    use SoftDeletes;

    protected $fillable = [
        'sort_order',
        'name',
        'chair',
        'email',
        'vice_chair',
        'members',
        'secretary',
        'is_active',
        'icon_key',
        'icon_path',
        'icon_library_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'icon_library_id' => 'integer',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\IconLibraryItem, $this>
     */
    public function iconLibraryItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IconLibraryItem::class, 'icon_library_id');
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

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithRosterForTerm(Builder $query, int $termId): Builder
    {
        return $query->where(function (Builder $query) use ($termId): void {
            $query->whereHas('memberships', fn (Builder $query) => $query->where('committee_term_id', $termId))
                ->orWhereHas('termSecretaries', fn (Builder $query) => $query->where('committee_term_id', $termId));
        });
    }

    public function hasRosterForTerm(int $termId): bool
    {
        return $this->memberships()->where('committee_term_id', $termId)->exists()
            || $this->termSecretaries()->where('committee_term_id', $termId)->exists();
    }

    public function chairDisplayName(?int $termId = null, bool $allowLegacy = true): string
    {
        $name = $this->roleDisplayName(CommitteeMembershipRole::Chair, $termId);

        if ($name !== '' || ! $allowLegacy) {
            return $name;
        }

        return trim((string) ($this->chair ?? ''));
    }

    public function viceChairDisplayName(?int $termId = null, bool $allowLegacy = true): string
    {
        $name = $this->roleDisplayName(CommitteeMembershipRole::ViceChair, $termId);

        if ($name !== '' || ! $allowLegacy) {
            return $name;
        }

        return trim((string) ($this->vice_chair ?? ''));
    }

    public function secretaryDisplayName(?int $termId = null, bool $allowLegacy = true): string
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

        if (! $allowLegacy) {
            return '';
        }

        return trim((string) ($this->secretary ?? ''));
    }

    /**
     * @return list<string>
     */
    public function memberDisplayNames(?int $termId = null, bool $allowLegacy = true): array
    {
        $termId ??= CommitteeTerm::query()->current()->value('id');

        if ($termId === null) {
            return $allowLegacy ? $this->legacyMemberNames() : [];
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

        if ($names !== [] || ! $allowLegacy) {
            return $names;
        }

        return $this->legacyMemberNames();
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
