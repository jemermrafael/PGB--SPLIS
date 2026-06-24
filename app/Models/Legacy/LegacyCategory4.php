<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LegacyCategory4 extends Model
{
    protected $connection = 'spreso';

    protected $table = 'zcategory4';

    public $timestamps = false;

    protected $primaryKey = 'ID';

    public $incrementing = false;
}
