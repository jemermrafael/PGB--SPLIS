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
            return $user->canDeleteResolutions() && $resolution->legacy_sp_id === null;
        }

        return MunicipalRequestAccess::userCanViewResolution($user, $resolution);
    }

    public function restore(User $user, Resolution $resolution): bool
    {
        return $user->isSuperadmin()
            && $resolution->legacy_sp_id === null
            && $resolution->trashed();
    }

    public function forceDelete(User $user, Resolution $resolution): bool
    {
        return $user->isSuperadmin()
            && $resolution->legacy_sp_id === null
            && $resolution->trashed();
    }

    public function delete(User $user, Resolution $resolution): bool
    {
        return $user->canDeleteResolutions()
            && $resolution->legacy_sp_id === null
            && ! $resolution->trashed();
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
