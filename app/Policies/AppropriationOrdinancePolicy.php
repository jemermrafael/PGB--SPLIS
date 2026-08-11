<?php

namespace App\Policies;

use App\Models\AppropriationOrdinance;
use App\Models\User;
use App\Support\MunicipalRequestAccess;
use App\Support\UserCapability;

class AppropriationOrdinancePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isMunicipalViewer()) {
            return false;
        }

        // Board members do not browse the full appropriation ordinance archive.
        if ($user->isBoardMember() && ! $user->isViceGovernorBoardMember()) {
            return false;
        }

        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::ORDINANCES);
        }

        return true;
    }

    public function view(User $user, AppropriationOrdinance $appropriationOrdinance): bool
    {
        return MunicipalRequestAccess::userCanViewAppropriationOrdinance($user, $appropriationOrdinance);
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDINANCES);
    }

    public function update(User $user, AppropriationOrdinance $appropriationOrdinance): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDINANCES);
    }

    public function delete(User $user, AppropriationOrdinance $appropriationOrdinance): bool
    {
        return $user->hasModuleCapability(UserCapability::ORDINANCES);
    }
}
