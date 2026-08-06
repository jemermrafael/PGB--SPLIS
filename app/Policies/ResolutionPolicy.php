<?php

namespace App\Policies;

use App\Models\Resolution;
use App\Models\User;
use App\Support\MunicipalRequestAccess;

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

        return true;
    }

    public function view(User $user, Resolution $resolution): bool
    {
        if ($resolution->trashed()) {
            return $user->canEncode() || $user->isSuperadmin();
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
        return $user->canEncode() && ! $resolution->trashed();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, Resolution $resolution): bool
    {
        return $user->canEncode() && ! $resolution->trashed();
    }
}
