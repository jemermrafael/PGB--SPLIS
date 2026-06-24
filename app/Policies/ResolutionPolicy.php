<?php

namespace App\Policies;

use App\Models\Resolution;
use App\Models\User;

class ResolutionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, Resolution $resolution): bool
    {
        return $user->canEncode() && $resolution->legacy_sp_id === null;
    }

    public function delete(User $user, Resolution $resolution): bool
    {
        return $user->canDeleteResolutions() && $resolution->legacy_sp_id === null;
    }
}
