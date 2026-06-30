<nav class="splis-agenda-timeline" aria-label="Agenda workflow">
    <ol class="splis-agenda-timeline-list">
        @foreach ($steps as $index => $step)
            <li class="splis-agenda-timeline-step splis-agenda-timeline-step--{{ $step['state'] }}">
                <div class="splis-agenda-timeline-marker" aria-hidden="true">
                    @if ($step['state'] === 'complete')
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    @else
                        <span>{{ $index + 1 }}</span>
                    @endif
                </div>
                <div class="splis-agenda-timeline-body">
                    <p class="splis-agenda-timeline-label">{{ $step['label'] }}</p>
                    @if ($step['date'])
                        <p class="splis-agenda-timeline-date">{{ $step['date'] }}</p>
                    @endif
                    @if ($step['detail'])
                        <p class="splis-agenda-timeline-detail">{{ $step['detail'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</nav>
