@php
    /** @var array $briefing */
    $next = $briefing['next_session'] ?? null;
    $myItems = $briefing['my_items_on_next_ob'] ?? collect();
    $deadlines = $briefing['deadline_agendas'] ?? collect();
    $deadlineDays = $briefing['deadline_days'] ?? $expiringSoonDays ?? 14;
    $deadlineCount = (int) ($briefing['deadline_count'] ?? $deadlines->count());
@endphp

<section class="splis-card mb-8 overflow-hidden border-brand-200 dark:border-brand-800">
    <div class="splis-card-header flex flex-wrap items-start justify-between gap-3 bg-brand-50/60 dark:bg-brand-950/30">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-300">Today’s briefing</p>
            <h2 class="splis-card-title">{{ now()->format('l, F j') }}</h2>
            <p class="splis-card-subtitle">
                @if ($briefing['session_today'] ?? false)
                    Session day — review your committee items below.
                @else
                    Next Session, your OB items, and upcoming Agenda deadlines.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            @if (($briefing['unread_notifications'] ?? 0) > 0)
                <a href="{{ route('notifications.index') }}" class="splis-badge splis-badge--muted">{{ $briefing['unread_notifications'] }} unread</a>
            @endif
            <a href="{{ route('board-member.agenda.index') }}" class="splis-badge splis-badge--muted">{{ number_format($briefing['pending_count'] ?? 0) }} pending</a>
        </div>
    </div>

    <div class="grid grid-cols-1 divide-y divide-slate-200 lg:grid-cols-2 lg:divide-x lg:divide-y-0 dark:divide-slate-700">
        <div class="p-4 sm:p-5">
            <h3 class="mb-2 text-sm font-semibold text-slate-900 dark:text-slate-100">Next Session</h3>
            @if ($next)
                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $next->session_date?->format('M j, Y') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $next->displayTitle() }}</p>
                @if ($next->venue)
                    <p class="mt-1 text-sm text-slate-500">{{ $next->venue }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('view', $next->obDocument)
                        <a href="{{ route('ob.document.print', $next) }}" target="_blank" class="splis-btn-primary !py-1.5 text-sm">View OB</a>
                    @endcan
                    <a href="{{ route('board-member.sessions.ics', $next) }}" class="splis-btn-ghost !py-1.5 text-sm">Add to Calendar</a>
                </div>
            @else
                <p class="text-sm text-slate-500">No upcoming scheduled Session.</p>
            @endif
        </div>

        <div class="p-4 sm:p-5">
            <h3 class="mb-2 text-sm font-semibold text-slate-900 dark:text-slate-100">My items on next OB</h3>
            @if ($myItems->isNotEmpty())
                <ul class="space-y-2 text-sm">
                    @foreach ($myItems->take(6) as $item)
                        <li>
                            <a href="{{ route('agenda.show', $item) }}" class="splis-link">
                                {{ $item->displayLabel() }} — {{ \Illuminate\Support\Str::limit($item->title ?: 'Untitled', 70) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if ($myItems->count() > 6)
                    <p class="mt-2 text-xs text-slate-500">+ {{ $myItems->count() - 6 }} more on this OB</p>
                @endif
            @else
                <p class="text-sm text-slate-500">No items from your Committees on the next Order of Business.</p>
            @endif
        </div>
    </div>

    <div class="border-t border-slate-200 dark:border-slate-700">
        <details class="splis-accordion">
            <summary class="splis-accordion-summary !px-4 !py-3 sm:!px-5">
                <div class="splis-accordion-summary-top">
                    <div>
                        <span class="splis-card-title text-sm">Agenda deadlines within {{ $deadlineDays }} days</span>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Committee Referrals approaching their due date.</p>
                    </div>
                    <span class="flex items-center gap-2">
                        <span class="splis-accordion-count">{{ number_format($deadlineCount) }}</span>
                        <svg class="splis-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </span>
                </div>
                @if ($deadlines->isNotEmpty())
                    <div class="splis-accordion-peek">
                        @foreach ($deadlines->take(2) as $agenda)
                            <div class="text-sm" onclick="event.stopPropagation()">
                                <a href="{{ route('agenda.show', $agenda) }}" class="splis-link font-medium">
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
                        @if ($deadlineCount > 2)
                            <p class="px-2 pt-1 text-xs text-slate-500">+ {{ number_format($deadlineCount - 2) }} more — expand to view all</p>
                        @endif
                    </div>
                @endif
            </summary>
            <div class="splis-accordion-body !px-4 !pb-4 sm:!px-5">
                @if ($deadlines->isNotEmpty())
                    <ul class="space-y-2 text-sm">
                        @foreach ($deadlines as $agenda)
                            <li>
                                <a href="{{ route('agenda.show', $agenda) }}" class="splis-link font-medium">
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
                    @if ($deadlineCount > $deadlines->count())
                        <p class="mt-3">
                            <a href="{{ route('board-member.agenda.index', ['expiring_soon' => 1]) }}" class="splis-link text-sm font-medium">
                                View all {{ number_format($deadlineCount) }} deadline items
                            </a>
                        </p>
                    @endif
                @else
                    <p class="text-sm text-slate-500">No Agenda deadlines within {{ $deadlineDays }} days.</p>
                @endif
            </div>
        </details>
    </div>
</section>
