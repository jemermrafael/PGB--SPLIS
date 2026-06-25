<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class SptrackFile extends Model
{
    protected $connection = 'sptrack';

    protected $table = 'Files';

    protected $primaryKey = 'FileId';

    public $timestamps = false;

    protected $casts = [
        'DateReceived' => 'datetime',
        'SPDateApproved' => 'datetime',
    ];
}
