<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function view(User $user, User $model): bool
    {
        return $user->canManageUsers();
    }

    public function create(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function update(User $user, User $model): bool
    {
        return $user->canManageUsers();
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->canManageUsers()) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($model->role === UserRole::Superadmin && $this->superadminCount() <= 1) {
            return false;
        }

        return true;
    }

    protected function superadminCount(): int
    {
        return User::query()->where('role', UserRole::Superadmin)->count();
    }
}
