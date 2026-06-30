<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['legacy_id', 'description'];

    /**
     * One option per description (case-insensitive), keeping the lowest id.
     *
     * @return Collection<int, static>
     */
    public static function forSelect(): Collection
    {
        return static::query()
            ->orderBy('description')
            ->orderBy('id')
            ->get()
            ->unique(fn (self $category) => strtolower(trim($category->description)))
            ->values();
    }

    public static function findOrCreateByDescription(?string $description): ?int
    {
        $description = trim((string) $description);
        if ($description === '') {
            return null;
        }

        $existing = static::query()
            ->whereRaw('LOWER(TRIM(description)) = ?', [strtolower($description)])
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return static::query()->create([
            'description' => $description,
        ])->id;
    }

    public function category2s(): HasMany
    {
        return $this->hasMany(Category2::class);
    }
}
