<?php

namespace App\Policies;

use App\Models\ReferenceMaterial;
use App\Models\User;

class ReferenceMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEncode();
    }

    public function update(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return $user->canEncode();
    }

    public function archive(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return $user->canEncode();
    }

    public function restore(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return $user->canEncode();
    }

    public function delete(User $user, ReferenceMaterial $referenceMaterial): bool
    {
        return $user->canEncode();
    }
}

