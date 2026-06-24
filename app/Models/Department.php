<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['code', 'description', 'abbreviation'];

    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class);
    }
}
