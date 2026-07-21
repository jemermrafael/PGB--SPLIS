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
            ->where('user_id', $authUser->id)
            ->visibleToRecipient($authUser);

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
    data-initial-notifications='@json($headerNotifications)'
    data-initial-count="{{ $headerNotificationCount }}"
    data-notifications-feed-url="{{ route('notifications.index') }}"
>
    <button type="button" id="splis-notify-trigger" class="splis-header-btn splis-header-btn-icon splis-notify-trigger" data-dropdown-trigger aria-expanded="false" aria-controls="splis-notify-panel" aria-haspopup="true" aria-label="Notifications">
        <x-icon name="bell" class="h-6 w-6" stroke-width="1.8" />
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
