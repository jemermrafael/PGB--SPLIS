<x-mail::message>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 24px 0;border-collapse:collapse;">
<tr>
<td style="padding:0 0 16px 0;border-bottom:1px solid #e8e5ef;">
<table cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;">
<tr>
<td style="vertical-align:middle;padding:0 12px 0 0;">
<img
    src="{{ asset('images/bataan-seal.png') }}"
    width="48"
    height="48"
    alt="Province of Bataan official seal"
    style="display:block;border-radius:9999px;border:0;"
>
</td>
<td style="vertical-align:middle;">
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;line-height:1.3;letter-spacing:0.04em;text-transform:uppercase;color:#6b7280;margin:0 0 2px 0;">
Legislative Information System
</div>
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.3;font-weight:600;color:#1f2937;margin:0;">
Sangguniang Panlalawigan
</div>
</td>
</tr>
</table>
</td>
</tr>
</table>

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
