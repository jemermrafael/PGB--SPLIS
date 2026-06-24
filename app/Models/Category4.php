<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category4 extends Model
{
    protected $fillable = ['category3_id', 'legacy_id', 'description'];

    public function category3(): BelongsTo
    {
        return $this->belongsTo(Category3::class);
    }
}
