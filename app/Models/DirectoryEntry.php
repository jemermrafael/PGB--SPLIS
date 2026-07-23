<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectoryEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'contact_number',
        'email',
        'designation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
