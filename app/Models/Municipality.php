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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function senderLabel(): string
    {
        return mb_convert_case(trim($this->description), MB_CASE_TITLE, 'UTF-8');
    }
}
