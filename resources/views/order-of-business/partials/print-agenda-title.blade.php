@if (filled($row['title'] ?? null) || filled($row['referral_note'] ?? null))
    @if (filled($row['title'] ?? null))
        <p>{!! nl2br(e($row['title'])) !!}</p>
    @endif
    @if (filled($row['filer_note'] ?? null))
        <p class="ob-print-filer-note">{{ $row['filer_note'] }}</p>
    @endif
    @if (filled($row['referral_note'] ?? null))
        <p @class([
            'ob-print-referral-note',
            'ob-print-referral-note--referred' => str_starts_with(trim((string) $row['referral_note']), '(Referred last'),
        ])>{!! nl2br(e($row['referral_note'])) !!}</p>
    @endif
@endif
