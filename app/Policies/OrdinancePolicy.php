<?php

namespace App\Policies;

use App\Models\Ordinance;
use App\Models\User;
use App\Support\MunicipalRequestAccess;

class OrdinancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ordinance $ordinance): bool
    {
        return MunicipalRequestAccess::userCanViewOrdinance($user, $ordinance);
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, Ordinance $ordinance): bool
    {
        return $user->canEncode();
    }

    public function delete(User $user, Ordinance $ordinance): bool
    {
        return $user->isSuperadmin() || $user->canEncode();
    }
}
