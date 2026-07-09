@if ($placements->isNotEmpty())
    <div>
        <p class="splis-detail-label">Order of Business placements</p>
        <ul class="mt-2 space-y-3">
            @foreach ($placements as $placement)
                @if ($placement->legislativeSession)
                    <li class="rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-700">
                        <p class="font-medium text-slate-900 dark:text-slate-100">
                            {{ $placement->legislativeSession->displayTitle() }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">{{ $placement->sectionLabel() }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                            @if ($placement->session_agenda_no)
                                <span>Agenda no. {{ $placement->session_agenda_no }}</span>
                            @endif
                            @if ($placement->legislativeSession->session_date)
                                <span>{{ $placement->legislativeSession->session_date->format('M j, Y') }}</span>
                            @endif
                        </div>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
@endif
