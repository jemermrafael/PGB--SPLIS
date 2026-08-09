<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function destroy(Request $request, ActivityLog $activityLog): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $activityLog);

        $action = $activityLog->action;

        UserNotification::query()
            ->where('activity_log_id', $activityLog->id)
            ->delete();

        $activityLog->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'History entry removed.',
                'action' => $action,
            ]);
        }

        return back()->with('status', 'History entry removed.');
    }
}
