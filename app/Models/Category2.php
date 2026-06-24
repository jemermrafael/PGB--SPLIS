<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category2 extends Model
{
    protected $fillable = ['category_id', 'legacy_id', 'description'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function category3s(): HasMany
    {
        return $this->hasMany(Category3::class);
    }
}
