<?php

namespace App\Policies;

use App\Models\BoardMemberCommitteeReport;
use App\Models\DirectoryEntry;
use App\Models\User;

class DirectoryEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canEncode() || $user->canAdmin();
    }

    public function view(User $user, DirectoryEntry $directoryEntry): bool
    {
        return $user->canEncode() || $user->canAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canEncode() || $user->canAdmin();
    }

    public function update(User $user, DirectoryEntry $directoryEntry): bool
    {
        return $user->canEncode() || $user->canAdmin();
    }

    public function delete(User $user, DirectoryEntry $directoryEntry): bool
    {
        return $user->canEncode() || $user->canAdmin();
    }
}
