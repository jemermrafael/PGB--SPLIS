<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $fillable = ['code', 'description', 'zipcode', 'district'];

    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class);
    }
}
