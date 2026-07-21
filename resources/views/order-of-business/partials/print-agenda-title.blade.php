@if (filled($row['title'] ?? null) || filled($row['referral_note'] ?? null))
    @if (filled($row['title'] ?? null))
        @php
            $formattedTitle = \App\Support\ObTitleMarkup::forTitle(
                is_string($row['title_html'] ?? null) ? $row['title_html'] : null,
                (string) $row['title'],
            );
        @endphp
        <p>
            @if ($formattedTitle !== null)
                {!! $formattedTitle !!}
            @else
                {!! nl2br(e($row['title'])) !!}
            @endif
        </p>
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
