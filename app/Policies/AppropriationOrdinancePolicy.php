<?php

namespace App\Policies;

use App\Models\AppropriationOrdinance;
use App\Models\User;

class AppropriationOrdinancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AppropriationOrdinance $appropriationOrdinance): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, AppropriationOrdinance $appropriationOrdinance): bool
    {
        return $user->canEncode();
    }

    public function delete(User $user, AppropriationOrdinance $appropriationOrdinance): bool
    {
        return $user->canEncode();
    }
}
