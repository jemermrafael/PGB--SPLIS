@php
    use App\Http\Controllers\UserNotificationController;
    use App\Models\User;
    use App\Models\UserNotification;

    $headerNotifications = collect();
    $headerNotificationCount = 0;

    $authUser = auth()->user();
    if ($authUser instanceof User && $authUser->receivesInAppNotifications()) {
        $controller = app(UserNotificationController::class);
        $query = UserNotification::query()
            ->withinRetention()
            ->where('user_id', $authUser->id);

        if ($authUser->canAdmin()) {
            $query->where('type', UserNotification::TYPE_ACTIVITY_LOG);
        } elseif ($authUser->isMunicipalViewer()) {
            $query->whereIn('type', UserNotification::municipalTypes());
        } else {
            $query->where('type', '!=', UserNotification::TYPE_ACTIVITY_LOG);
        }

        $headerNotificationCount = (clone $query)->whereNull('read_at')->count();
        $headerNotifications = $query
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (UserNotification $notification) => $controller->notificationPayload($notification))
            ->values();
    }
@endphp

<div
    class="splis-notify-wrap"
    data-dropdown
    data-dropdown-click-only
    data-initial-notifications='@json($headerNotifications)'
    data-initial-count="{{ $headerNotificationCount }}"
    data-notifications-feed-url="{{ route('notifications.index') }}"
>
    <button type="button" id="splis-notify-trigger" class="splis-header-btn splis-header-btn-icon splis-notify-trigger" data-dropdown-trigger aria-expanded="false" aria-controls="splis-notify-panel" aria-haspopup="true" aria-label="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
        </svg>
        <span id="splis-notify-badge" class="splis-notify-badge" @if ($headerNotificationCount <= 0) hidden @endif>{{ $headerNotificationCount > 99 ? '99+' : $headerNotificationCount }}</span>
    </button>
    <div id="splis-notify-panel" class="splis-notify-panel" data-dropdown-panel role="dialog" aria-label="Notifications">
        <div class="splis-notify-panel-header">
            <h3>Notifications</h3>
            <button type="button" id="splis-notify-mark-all" class="splis-notify-mark-all" @if ($headerNotificationCount <= 0) hidden @endif>Mark all read</button>
        </div>
        <div id="splis-notify-list" class="splis-notify-list">
            @if ($headerNotifications->isEmpty())
                <p class="splis-notify-empty">No notifications yet.</p>
            @else
                @foreach ($headerNotifications as $notification)
                    @include('notifications.partials.item', ['notification' => $notification])
                @endforeach
            @endif
        </div>
        <div class="splis-notify-panel-footer">
            <a href="{{ route('notifications.index') }}" class="splis-notify-see-all">See all notifications</a>
        </div>
    </div>
</div>
