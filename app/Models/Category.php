<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['legacy_id', 'description'];

    public function category2s(): HasMany
    {
        return $this->hasMany(Category2::class);
    }
}
