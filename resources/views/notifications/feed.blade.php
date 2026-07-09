@extends('layouts.app')

@section('title', 'Notifications — '.config('app.name'))

@section('content')
<div class="max-w-2xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Notifications</h1>
            <p class="splis-page-subtitle">
                @if ($unreadCount > 0)
                    {{ $unreadCount }} unread
                @else
                    You are all caught up
                @endif
            </p>
        </div>
        @if ($unreadCount > 0)
            <button type="button" id="notifications-feed-mark-all" class="splis-btn-secondary">Mark all read</button>
        @endif
    </div>

    <div
        id="notifications-feed"
        class="splis-card overflow-hidden"
        data-next-before-id="{{ $nextBeforeId ?? '' }}"
        data-has-more="{{ $hasMore ? '1' : '0' }}"
    >
        <div id="notifications-feed-list" class="splis-notify-feed-list">
            @forelse ($notifications as $notification)
                @include('notifications.partials.item', ['notification' => $notification])
            @empty
                <p class="splis-notify-empty">No notifications yet.</p>
            @endforelse
        </div>

        @if ($hasMore)
            <div class="splis-notify-feed-footer">
                <button type="button" id="notifications-feed-load-more" class="splis-btn-secondary w-full">
                    See previous notifications
                </button>
            </div>
        @endif
    </div>
</div>
@endsection
