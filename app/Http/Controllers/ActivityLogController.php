<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;

class ActivityLogController extends Controller
{
    public function destroy(ActivityLog $activityLog): RedirectResponse
    {
        $this->authorize('delete', $activityLog);

        UserNotification::query()
            ->where('activity_log_id', $activityLog->id)
            ->delete();

        $activityLog->delete();

        return back()->with('status', 'History entry removed.');
    }
}
