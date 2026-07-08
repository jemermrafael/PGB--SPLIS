<?php

namespace App\Policies;

use App\Models\AgendaItemVersion;
use App\Models\User;

class AgendaItemVersionPolicy
{
    public function delete(User $user, AgendaItemVersion $version): bool
    {
        return $user->isSuperadmin();
    }
}
