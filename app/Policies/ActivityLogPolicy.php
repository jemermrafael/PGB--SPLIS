<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return $user->isSuperadmin();
    }
}
