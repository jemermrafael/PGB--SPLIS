<?php

namespace App\Policies;

use App\Models\AppropriationOrdinance;
use App\Models\User;
use App\Support\MunicipalRequestAccess;

class AppropriationOrdinancePolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isMunicipalViewer();
    }

    public function view(User $user, AppropriationOrdinance $appropriationOrdinance): bool
    {
        return MunicipalRequestAccess::userCanViewAppropriationOrdinance($user, $appropriationOrdinance);
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
