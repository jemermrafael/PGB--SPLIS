<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommitteeTerm extends Model
{
    protected $fillable = [
        'label',
        'year_from',
        'year_to',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'year_from' => 'integer',
            'year_to' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CommitteeMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CommitteeMembership::class);
    }

    /**
     * @return HasMany<BoardMemberTerm, $this>
     */
    public function boardMemberAssignments(): HasMany
    {
        return $this->hasMany(BoardMemberTerm::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_current')->orderByDesc('year_from')->orderByDesc('id');
    }

    public static function currentOrCreate(): self
    {
        $current = self::query()->current()->first();

        if ($current !== null) {
            return $current;
        }

        $defaults = config('board_members.current_term', []);

        return self::query()->create([
            'label' => $defaults['label'] ?? 'Current term',
            'year_from' => $defaults['year_from'] ?? (int) now()->format('Y'),
            'year_to' => $defaults['year_to'] ?? null,
            'is_current' => true,
        ]);
    }
}
