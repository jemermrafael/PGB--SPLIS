@if ($expiringSoonAgendas->isNotEmpty())
    @php
        $expiringCount = (int) ($stats['expiring_soon'] ?? $expiringSoonAgendas->count());
    @endphp
    <div class="splis-card mb-6 overflow-hidden border-l-4 border-l-amber-500">
        <details class="splis-accordion">
            <summary class="splis-accordion-summary">
                <div class="splis-accordion-summary-top">
                    <div>
                        <span class="splis-card-title">Agenda deadlines within {{ $expiringSoonDays }} days</span>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $expiringSubtitle ?? 'Committee Referrals approaching their due date.' }}</p>
                    </div>
                    <span class="flex items-center gap-2">
                        <span class="splis-accordion-count">{{ number_format($expiringCount) }}</span>
                        <svg class="splis-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </span>
                </div>
                <div class="splis-accordion-peek">
                    @foreach ($expiringSoonAgendas->take(2) as $agenda)
                        <div class="text-sm" onclick="event.stopPropagation()">
                            <a href="{{ route($requestShowRoute ?? 'agenda.show', $agenda) }}" class="splis-link font-medium">
                                {{ $agenda->displayLabel() }}
                            </a>
                            <span class="text-slate-600 dark:text-slate-300">
                                — due {{ $agenda->due_date?->format('M j, Y') }}
                                @if (is_numeric($agenda->days_left_label))
                                    ({{ $agenda->days_left_label }} days left)
                                @endif
                            </span>
                        </div>
                    @endforeach
                    @if ($expiringCount > 2)
                        <p class="px-2 pt-1 text-xs text-slate-500">+ {{ number_format($expiringCount - 2) }} more — expand to view all</p>
                    @endif
                </div>
            </summary>
            <div class="splis-accordion-body">
                <ul class="space-y-2 text-sm">
                    @foreach ($expiringSoonAgendas as $agenda)
                        <li>
                            <a href="{{ route($requestShowRoute ?? 'agenda.show', $agenda) }}" class="splis-link font-medium">
                                {{ $agenda->displayLabel() }}
                            </a>
                            <span class="text-slate-600 dark:text-slate-300">
                                — due {{ $agenda->due_date?->format('M j, Y') }}
                                @if (is_numeric($agenda->days_left_label))
                                    ({{ $agenda->days_left_label }} days left)
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($expiringCount > $expiringSoonAgendas->count())
                    <p class="mt-3">
                        <a href="{{ route($requestsIndexRoute ?? 'board-member.agenda.index', ['expiring_soon' => 1]) }}" class="splis-link text-sm font-medium">
                            View all {{ number_format($expiringCount) }} expiring items
                        </a>
                    </p>
                @endif
            </div>
        </details>
    </div>
@endif
