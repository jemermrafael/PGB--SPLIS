@php
    $signOff = $branding['sign_off'] ?? 'Thanks,';
    $signature = $branding['signature'] ?? config('app.name');
    $headerEyebrow = $branding['header_eyebrow'] ?? 'Legislative Information System';
    $headerTitle = $branding['header_title'] ?? 'Sangguniang Panlalawigan';
    $heroUrl = $heroBackgroundUrl ?: url('/images/dashboard-hero-bg.png');
    $seal = $sealUrl ?: url('/images/bataan-seal.png');
    $heroStyle = implode('', [
        'padding:22px 24px;',
        'border-radius:8px 8px 0 0;',
        'border-bottom:1px solid rgba(212,168,67,0.35);',
        'background-color:#061525;',
        "background-image:linear-gradient(100deg, #061525 0%, #061525 38%, rgba(6,21,37,0.92) 52%, rgba(12,35,64,0.55) 72%, rgba(12,35,64,0.2) 100%), url('{$heroUrl}');",
        'background-position:center, 72% center;',
        'background-size:cover, cover;',
        'background-repeat:no-repeat;',
    ]);
@endphp
<x-mail::message>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 24px 0;border-collapse:collapse;">
<tr>
<td style="{{ $heroStyle }}">
<table cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;">
<tr>
<td style="vertical-align:middle;padding:0 14px 0 0;">
<img
    src="{{ $seal }}"
    width="48"
    height="48"
    alt="Province of Bataan official seal"
    style="display:block;border-radius:9999px;border:0;"
>
</td>
<td style="vertical-align:middle;">
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;line-height:1.3;letter-spacing:0.16em;text-transform:uppercase;color:#d4a843;margin:0 0 4px 0;font-weight:600;">
{{ $headerEyebrow }}
</div>
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:18px;line-height:1.25;font-weight:700;color:#f8fafc;margin:0;">
{{ $headerTitle }}
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

{{ $signOff }}<br>
{{ $signature }}
</x-mail::message>
