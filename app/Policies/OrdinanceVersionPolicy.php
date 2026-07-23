<?php

namespace App\Policies;

use App\Models\OrdinanceVersion;
use App\Models\User;

class OrdinanceVersionPolicy
{
    public function delete(User $user, OrdinanceVersion $version): bool
    {
        return $user->isSuperadmin();
    }
}
