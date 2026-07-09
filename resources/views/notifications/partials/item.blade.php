<article @class(['splis-notify-item', 'is-unread' => $notification['unread']]) data-notify-id="{{ $notification['id'] }}">
    <div class="splis-notify-item-main">
        <p class="splis-notify-item-title">{{ $notification['title'] }}</p>
        @if ($notification['body'])
            <p class="splis-notify-item-body">{{ $notification['body'] }}</p>
        @endif
        <p class="splis-notify-item-time">{{ $notification['created_at'] }}</p>
        @if ($notification['link'])
            <a href="{{ $notification['link'] }}" class="splis-notify-item-link" data-notify-link="{{ $notification['id'] }}">{{ $notification['link_label'] }}</a>
        @endif
    </div>
    @if ($notification['unread'])
        <button type="button" class="splis-notify-item-dismiss" data-notify-dismiss="{{ $notification['id'] }}" aria-label="Dismiss">×</button>
    @else
        <span class="splis-notify-read-label">Read</span>
    @endif
</article>
