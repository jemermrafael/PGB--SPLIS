@foreach ($blocks as $block)
    @switch($block->type->value)
        @case('heading')
            <h2 class="ob-print-heading">{{ $block->content['text'] ?? '' }}</h2>
            @break
        @case('committee_group')
            <h3 class="ob-print-committee">{{ $block->content['text'] ?? '' }}</h3>
            @break
        @case('paragraph')
            @if (filled($block->content['text'] ?? null))
                <p class="ob-print-paragraph">{!! nl2br(e($block->content['text'])) !!}</p>
            @endif
            @break
        @case('agenda_line')
            <div class="ob-print-agenda-line">
                <p class="ob-print-agenda-no">Agenda No. {{ \App\Support\ObAgendaSnapshot::displayAgendaNo($block->content ?? []) }}</p>
                @if (filled($block->content['date_received'] ?? null))
                    <p class="ob-print-agenda-meta">Date of Receipt: {{ $block->content['date_received'] }}</p>
                @endif
                @if (filled($block->content['prescription'] ?? null) && strcasecmp(trim((string) $block->content['prescription']), 'No due date') !== 0)
                    <p class="ob-print-agenda-meta">Prescription: {{ $block->content['prescription'] }}</p>
                @endif
                @if (filled($block->content['title'] ?? null))
                    <p class="ob-print-agenda-title">{{ $block->content['title'] }}</p>
                @endif
                @if (filled($block->content['referral_note'] ?? null))
                    <p class="ob-print-agenda-meta">{{ $block->content['referral_note'] }}</p>
                @endif
            </div>
            @break
        @case('table')
            @php
                $headers = $block->content['headers'] ?? [];
                $rows = $block->content['rows'] ?? [];
            @endphp
            @if ($headers !== [] || $rows !== [])
                <table class="ob-print-table">
                    @if ($headers !== [])
                        <thead>
                            <tr>
                                @foreach ($headers as $header)
                                    <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                    @endif
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                @foreach ($headers as $header)
                                    <td>&nbsp;</td>
                                @endforeach
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            @break
        @case('page_break')
            <div class="ob-print-page-break" aria-hidden="true"></div>
            @break
    @endswitch
@endforeach
