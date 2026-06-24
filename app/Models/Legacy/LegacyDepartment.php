<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LegacyDepartment extends Model
{
    protected $connection = 'spreso';

    protected $table = 'zdepartment';

    public $timestamps = false;

    protected $primaryKey = 'Code';

    public $incrementing = false;
}
