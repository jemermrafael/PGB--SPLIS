<?php

namespace App\Policies;

use App\Models\ResolutionVersion;
use App\Models\User;

class ResolutionVersionPolicy
{
    public function delete(User $user, ResolutionVersion $version): bool
    {
        return $user->isSuperadmin();
    }
}
