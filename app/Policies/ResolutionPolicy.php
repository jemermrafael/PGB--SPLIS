<?php

namespace App\Policies;

use App\Models\Resolution;
use App\Models\User;
use App\Support\MunicipalRequestAccess;
use App\Support\UserCapability;

class ResolutionPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isMunicipalViewer()) {
            return false;
        }

        // Regular board members use /my-resolutions (scoped to their committees).
        if ($user->isBoardMember() && ! $user->isViceGovernorBoardMember()) {
            return false;
        }

        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::RESOLUTIONS);
        }

        return true;
    }

    public function view(User $user, Resolution $resolution): bool
    {
        if ($resolution->trashed()) {
            return $user->hasModuleCapability(UserCapability::RESOLUTIONS) || $user->isSuperadmin();
        }

        return MunicipalRequestAccess::userCanViewResolution($user, $resolution);
    }

    public function restore(User $user, Resolution $resolution): bool
    {
        return $user->isSuperadmin() && $resolution->trashed();
    }

    public function forceDelete(User $user, Resolution $resolution): bool
    {
        return $user->isSuperadmin() && $resolution->trashed();
    }

    public function delete(User $user, Resolution $resolution): bool
    {
        return $user->hasModuleCapability(UserCapability::RESOLUTIONS) && ! $resolution->trashed();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::RESOLUTIONS);
    }

    public function update(User $user, Resolution $resolution): bool
    {
        return $user->hasModuleCapability(UserCapability::RESOLUTIONS) && ! $resolution->trashed();
    }
}
