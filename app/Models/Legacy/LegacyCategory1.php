<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LegacyCategory1 extends Model
{
    protected $connection = 'spreso';

    protected $table = 'zcategory1';

    public $timestamps = false;

    protected $primaryKey = 'ID';

    public $incrementing = false;
}
