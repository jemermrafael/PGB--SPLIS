<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category3 extends Model
{
    protected $fillable = ['category2_id', 'legacy_id', 'description'];

    public function category2(): BelongsTo
    {
        return $this->belongsTo(Category2::class);
    }

    public function category4s(): HasMany
    {
        return $this->hasMany(Category4::class);
    }
}
