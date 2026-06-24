<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LegacyUser extends Model
{
    protected $connection = 'spreso';

    protected $table = 'zuser';

    public $timestamps = false;

    protected $primaryKey = 'PK';

    public $incrementing = false;
}
