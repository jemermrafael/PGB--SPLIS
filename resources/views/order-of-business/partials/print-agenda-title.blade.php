@if (filled($row['title'] ?? null) || filled($row['referral_note'] ?? null))
    @if (filled($row['title'] ?? null))
        <p>{!! nl2br(e($row['title'])) !!}</p>
    @endif
    @if (filled($row['referral_note'] ?? null))
        <p>{!! nl2br(e($row['referral_note'])) !!}</p>
    @endif
@endif
