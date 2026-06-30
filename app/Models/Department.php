<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['code', 'description', 'abbreviation'];

    /**
     * @return Collection<int, static>
     */
    public static function forSelect(): Collection
    {
        return static::query()
            ->orderBy('description')
            ->orderBy('id')
            ->get()
            ->unique(fn (self $department) => strtolower(trim($department->description)))
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

        $code = ((int) static::query()->max('code')) + 1;
        while (static::query()->where('code', $code)->exists()) {
            $code++;
        }

        return static::query()->create([
            'code' => $code,
            'description' => $description,
        ])->id;
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class);
    }
}
