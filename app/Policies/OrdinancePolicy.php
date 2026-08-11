<?php

namespace App\Policies;

use App\Models\Ordinance;
use App\Models\User;
use App\Support\MunicipalRequestAccess;
use App\Support\UserCapability;

class OrdinancePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::ORDINANCES);
        }

        return true;
    }

    public function view(User $user, Ordinance $ordinance): bool
    {
        return MunicipalRequestAccess::userCanViewOrdinance($user, $ordinance);
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDINANCES);
    }

    public function update(User $user, Ordinance $ordinance): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDINANCES);
    }

    public function delete(User $user, Ordinance $ordinance): bool
    {
        return $user->isSuperadmin() || $user->hasModuleCapability(UserCapability::ORDINANCES);
    }
}
