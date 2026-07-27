<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DirectoryCategory extends Model
{
    public const PROVINCIAL_BOARD = 'Provincial Board';

    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function isProvincialBoard(): bool
    {
        return strcasecmp(trim($this->name), self::PROVINCIAL_BOARD) === 0;
    }

    /**
     * @return HasMany<DirectoryEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(DirectoryEntry::class);
    }
}
