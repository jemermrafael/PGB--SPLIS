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

    /**
     * Normalize category labels for analytics charts (merge near-duplicates).
     */
    public static function analyticsGroupLabel(?string $description): string
    {
        $label = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $description) ?? ''));
        if ($label === '') {
            return 'Unknown';
        }

        if (
            $label === 'SUPPLEMENTAL'
            || preg_match('/^SUPPLEMENTAL\s+BUDGET(\s+NO\.?\s*\d+)?$/u', $label) === 1
        ) {
            return 'SUPPLEMENTAL BUDGET';
        }

        if (str_starts_with($label, 'SUPPLEMENTAL INVESTMENT')) {
            return 'SUPPLEMENTAL INVESTMENT PROGRAM';
        }

        return $label;
    }

    public function category2s(): HasMany
    {
        return $this->hasMany(Category2::class);
    }
}
