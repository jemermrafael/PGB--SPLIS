<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LegacyMunicipality extends Model
{
    protected $connection = 'spreso';

    protected $table = 'zmunicipality';

    public $timestamps = false;

    protected $primaryKey = 'Code';

    public $incrementing = false;
}
