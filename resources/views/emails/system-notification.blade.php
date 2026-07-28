<x-mail::message>
# {{ $notificationTitle }}

{!! $notificationBody !!}

@if ($actionUrl)
<x-mail::button :url="$actionUrl">
{{ $actionLabel ?: 'View details' }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
