@if ($placements->isNotEmpty())
    <div>
        <p class="splis-detail-label">Order of Business placements</p>
        <ul class="mt-2 space-y-3">
            @foreach ($placements as $placement)
                @if ($placement->legislativeSession)
                    <li class="rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-700">
                        <a href="{{ route('ob.sessions.show', $placement->legislativeSession) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                            {{ $placement->legislativeSession->displayTitle() }}
                        </a>
                        <p class="mt-1 text-xs text-slate-500">{{ $placement->sectionLabel() }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                            @if ($placement->session_agenda_no)
                                <span>Agenda no. {{ $placement->session_agenda_no }}</span>
                            @endif
                            @if ($placement->agendaItemVersion)
                                <span>Version v{{ $placement->agendaItemVersion->version_no }}</span>
                            @endif
                            <span>{{ $placement->created_at?->format('M j, Y') }}</span>
                        </div>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
@endif
