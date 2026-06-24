<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LegacyCategory2 extends Model
{
    protected $connection = 'spreso';

    protected $table = 'zcategory2';

    public $timestamps = false;

    protected $primaryKey = 'ID';

    public $incrementing = false;
}
