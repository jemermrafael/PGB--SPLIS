<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BoardMember extends Model
{
    protected $fillable = [
        'name',
        'honorific',
        'district',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CommitteeMembership, $this>
     */
    public function committeeMemberships(): HasMany
    {
        return $this->hasMany(CommitteeMembership::class);
    }

    /**
     * @return BelongsToMany<Ordinance, $this>
     */
    public function ordinances(): BelongsToMany
    {
        return $this->belongsToMany(Ordinance::class, 'ordinance_board_member')
            ->withPivot(['role', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function sessionAttendances(): HasMany
    {
        return $this->hasMany(SessionAttendance::class);
    }

    /**
     * @return HasMany<BoardMemberTerm, $this>
     */
    public function termAssignments(): HasMany
    {
        return $this->hasMany(BoardMemberTerm::class);
    }

    /**
     * @return HasOne<BoardMemberTerm, $this>
     */
    public function termAssignment(): HasOne
    {
        return $this->hasOne(BoardMemberTerm::class);
    }

    public function districtForTerm(?int $termId): ?string
    {
        if ($termId === null) {
            return $this->district;
        }

        $assignment = $this->relationLoaded('termAssignments')
            ? $this->termAssignments->firstWhere('committee_term_id', $termId)
            : $this->termAssignments()->where('committee_term_id', $termId)->first();

        return $assignment?->district ?? $this->district;
    }

    public function isActiveForTerm(?int $termId): bool
    {
        if ($termId === null) {
            return (bool) $this->is_active;
        }

        $assignment = $this->relationLoaded('termAssignments')
            ? $this->termAssignments->firstWhere('committee_term_id', $termId)
            : $this->termAssignments()->where('committee_term_id', $termId)->first();

        return $assignment !== null ? $assignment->is_active : (bool) $this->is_active;
    }

    public function displayName(): string
    {
        return $this->formattedName($this->honorific);
    }

    public function officialName(): string
    {
        $honorific = trim((string) ($this->honorific ?? ''));

        if ($honorific === '') {
            $honorific = (string) config('board_members.default_honorific', 'Hon.');
        }

        return $this->formattedName($honorific);
    }

    public function orderOfBusinessName(): string
    {
        $name = trim($this->name);

        return $name !== '' ? 'Board Member '.$name : '';
    }

    protected function formattedName(?string $honorific): string
    {
        $honorific = trim((string) ($honorific ?? ''));
        $name = trim($this->name);

        return $honorific !== '' ? "{$honorific} {$name}" : $name;
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
        return $query->orderBy('name');
    }
}
