<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
