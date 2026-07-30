<?php

namespace App\Policies;

use App\Models\AppropriationOrdinanceVersion;
use App\Models\User;

class AppropriationOrdinanceVersionPolicy
{
    public function delete(User $user, AppropriationOrdinanceVersion $version): bool
    {
        return $user->isSuperadmin();
    }
}
