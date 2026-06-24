<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LegacyCategory3 extends Model
{
    protected $connection = 'spreso';

    protected $table = 'zcategory3';

    public $timestamps = false;

    protected $primaryKey = 'ID';

    public $incrementing = false;
}
