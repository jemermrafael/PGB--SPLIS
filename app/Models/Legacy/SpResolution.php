<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class SpResolution extends Model
{
    protected $connection = 'spreso';

    protected $table = 'sp';

    public $timestamps = false;

    protected $primaryKey = 'ID';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'Province' => 'boolean',
            'Series' => 'integer',
            'Amount' => 'integer',
        ];
    }
}
