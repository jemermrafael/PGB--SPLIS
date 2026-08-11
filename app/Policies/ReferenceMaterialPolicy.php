<?php

namespace App\Policies;

use App\Models\ReferenceMaterial;
use App\Models\User;
use App\Support\UserCapability;

class ReferenceMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::REFERENCES);
        }

        return true;
    }

    public function view(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        if ($user->canEncode() || $user->canAdmin()) {
            return $user->hasModuleCapability(UserCapability::REFERENCES);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasModuleCapability(UserCapability::REFERENCES);
    }

    public function update(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return $user->hasModuleCapability(UserCapability::REFERENCES);
    }

    public function archive(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return $user->hasModuleCapability(UserCapability::REFERENCES);
    }

    public function restore(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return $user->hasModuleCapability(UserCapability::REFERENCES);
    }

    public function delete(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return $user->hasModuleCapability(UserCapability::REFERENCES);
    }
}
