<?php

namespace App\Policies;

use App\Models\Resolution;
use App\Models\User;
use App\Support\MunicipalRequestAccess;

class ResolutionPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isMunicipalViewer();
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

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, Resolution $resolution): bool
    {
        return $user->canEncode() && ! $resolution->trashed();
    }
}
