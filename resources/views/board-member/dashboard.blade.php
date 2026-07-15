@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
<div class="splis-dashboard max-w-6xl">
    <div class="splis-dashboard-hero mb-8">
        <div class="splis-dashboard-hero-glow" aria-hidden="true"></div>
        <div class="relative">
            <p class="splis-dashboard-hero-eyebrow">Board Member Portal</p>
            <h1 class="splis-page-title text-white">Welcome, Hon. {{ $user->name }}</h1>
            <p class="splis-dashboard-hero-subtitle">Your Committee Agenda, Session Calendar, and Order of Business.</p>
        </div>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a Board Member profile yet. Please contact the SP office administrator.</div>
    @endif

    @include('board-member.agenda.partials.expiring-soon', [
        'expiringSoonAgendas' => $expiringSoonAgendas,
        'expiringSoonDays' => $expiringSoonDays,
        'stats' => $agendaStats,
    ])

    <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="splis-card overflow-hidden">
            <details class="splis-accordion">
                <summary class="splis-accordion-summary">
                    <div class="splis-accordion-summary-top">
                        <span class="splis-card-title">Your Committees</span>
                        <span class="flex items-center gap-2">
                            <span class="splis-accordion-count">{{ $committeeAssignments->count() }}</span>
                            <svg class="splis-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </span>
                    </div>
                    @if ($committeeAssignments->count() > 0)
                        <div class="splis-accordion-peek">
                            @foreach ($committeeAssignments->take(2) as $assignment)
                                <div class="splis-committee-row" onclick="event.stopPropagation()">
                                    <a href="{{ route('board-member.agenda.committee', $assignment['committee']) }}" class="splis-link font-medium">
                                        {{ $assignment['committee']->name }}
                                    </a>
                                    <span class="splis-badge splis-badge--muted">{{ $assignment['role_label'] }}</span>
                                </div>
                            @endforeach
                            @if ($committeeAssignments->count() > 2)
                                <p class="px-2 pt-1 text-xs text-slate-500">+ {{ $committeeAssignments->count() - 2 }} more — expand to view all</p>
                            @endif
                        </div>
                    @endif
                </summary>
                <div class="splis-accordion-body">
                    @forelse ($committeeAssignments as $assignment)
                        <div class="splis-committee-row">
                            <a href="{{ route('board-member.agenda.committee', $assignment['committee']) }}" class="splis-link font-medium">
                                {{ $assignment['committee']->name }}
                            </a>
                            <span class="splis-badge splis-badge--muted">{{ $assignment['role_label'] }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No committee assignments for the current term.</p>
                    @endforelse
                </div>
            </details>
        </div>

        <div class="splis-card">
            <div class="splis-card-header flex items-center justify-between">
                <h2 class="splis-card-title">Session Calendar</h2>
                <a href="{{ route('ob.sessions.index') }}" class="splis-link text-sm">All Sessions</a>
            </div>
            <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse ($sessions as $session)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ $session->session_date->format('M j, Y') }}</p>
                            <p class="text-sm text-slate-500">{{ $session->displayTitle() }}</p>
                        </div>
                        @can('view', $session->obDocument)
                            <a href="{{ route('ob.document.print', $session) }}" target="_blank" class="splis-link text-sm">View OB</a>
                        @endcan
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">No Upcoming Sessions Scheduled.</li>
                @endforelse
            </ul>
            @if ($sessions->isNotEmpty())
                @php
                    $committeeNames = $committeeAssignments
                        ->pluck('committee.name')
                        ->filter()
                        ->map(fn ($name) => mb_strtolower((string) $name))
                        ->values();

                    $scheduledAgendaCount = $sessions->sum(function ($session) use ($committeeNames) {
                        return $session->obDocument?->blocks
                            ?->filter(fn ($block) => $block->agendaItem !== null)
                            ->map(fn ($block) => $block->agendaItem)
                            ->filter(function ($agendaItem) use ($committeeNames) {
                                if ($committeeNames->isEmpty()) {
                                    return false;
                                }

                                $committee = mb_strtolower((string) ($agendaItem->committee_referred ?? ''));

                                return $committeeNames->contains(fn ($name) => $name !== '' && str_contains($committee, $name));
                            })
                            ->pluck('id')
                            ->filter()
                            ->unique()
                            ->count() ?? 0;
                    });
                @endphp
                <details class="splis-accordion border-t border-slate-200 dark:border-slate-700">
                    <summary class="splis-accordion-summary !px-4 !py-3">
                        <div class="splis-accordion-summary-top">
                            <span class="splis-card-title text-sm">My Schedules Agendas</span>
                            <span class="flex items-center gap-2">
                                <span class="splis-accordion-count">{{ $scheduledAgendaCount }}</span>
                                <svg class="splis-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                </svg>
                            </span>
                        </div>
                    </summary>
                    <div class="splis-accordion-body !px-4 !pb-4 !pt-0">
                        @foreach ($sessions as $session)
                            @php
                                $sessionAgendas = $session->obDocument?->blocks
                                    ?->filter(fn ($block) => $block->agendaItem !== null)
                                    ->map(fn ($block) => $block->agendaItem)
                                    ->filter(function ($agendaItem) use ($committeeNames) {
                                        if ($committeeNames->isEmpty()) {
                                            return false;
                                        }

                                        $committee = mb_strtolower((string) ($agendaItem->committee_referred ?? ''));

                                        return $committeeNames->contains(fn ($name) => $name !== '' && str_contains($committee, $name));
                                    })
                                    ->filter()
                                    ->unique('id')
                                    ->values() ?? collect();
                            @endphp
                            @if ($sessionAgendas->isNotEmpty())
                                <details class="splis-filter-advanced mb-2">
                                    <summary class="splis-filter-advanced-toggle">
                                        <span>{{ $session->displayTitle() }}</span>
                                        <span class="text-xs text-slate-500">{{ $sessionAgendas->count() }} agenda item(s)</span>
                                    </summary>
                                    <div class="splis-filter-advanced-panel">
                                        <ul class="space-y-2 text-sm">
                                            @foreach ($sessionAgendas as $agendaItem)
                                                <li class="flex items-start justify-between gap-3">
                                                    <a href="{{ route('agenda.show', $agendaItem) }}" class="splis-link">
                                                        {{ $agendaItem->displayLabel() }} — {{ \Illuminate\Support\Str::limit($agendaItem->title ?: 'Untitled', 90) }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </details>
                            @endif
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>

    <div
        id="bm-dashboard-agenda-search"
        class="splis-card mb-8"
        data-search-url="{{ route('board-member.agenda.search') }}"
        data-compact="1"
    >
        <div class="splis-card-header flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="splis-card-title">Agenda Referred to Your Committees</h2>
                <p class="splis-card-subtitle">Recent Referrals</p>
            </div>
            <a href="{{ route('board-member.agenda.index') }}" class="splis-link text-sm">My agenda</a>
        </div>

        <div class="splis-card-body border-b border-slate-200 dark:border-slate-700">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="splis-stat splis-stat--gold text-left">
                    <p class="splis-stat-label">Pending</p>
                    <p class="splis-stat-value" id="bm-agenda-stat-pending">{{ number_format($agendaStats['pending']) }}</p>
                    <p class="splis-stat-meta">Awaiting action</p>
                </div>
                <div class="splis-stat splis-stat--amber text-left">
                    <p class="splis-stat-label">Expiring soon</p>
                    <p class="splis-stat-value" id="bm-agenda-stat-expiring-soon">{{ number_format($agendaStats['expiring_soon']) }}</p>
                    <p class="splis-stat-meta">Within {{ $expiringSoonDays }} days</p>
                </div>
                <div class="splis-stat splis-stat--brand text-left">
                    <p class="splis-stat-label">Due soon</p>
                    <p class="splis-stat-value" id="bm-agenda-stat-due-soon">{{ number_format($agendaStats['due_soon']) }}</p>
                    <p class="splis-stat-meta">Within 7 days</p>
                </div>
                <div class="splis-stat splis-stat--green text-left">
                    <p class="splis-stat-label">Accomplished</p>
                    <p class="splis-stat-value" id="bm-agenda-stat-done">{{ number_format($agendaStats['done']) }}</p>
                    <p class="splis-stat-meta">Done items</p>
                </div>
                <div class="splis-stat splis-stat--sky text-left">
                    <p class="splis-stat-label">Lapsed</p>
                    <p class="splis-stat-value" id="bm-agenda-stat-lapsed">{{ number_format($agendaStats['lapsed']) }}</p>
                    <p class="splis-stat-meta">Deemed approved</p>
                </div>
            </div>
        </div>

        <div class="splis-card-body border-b border-slate-200 dark:border-slate-700">
            <form id="bm-dashboard-agenda-search-form" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[10rem] flex-1">
                    <label class="splis-label" for="bm-dashboard-agenda-q">Search</label>
                    <input type="text" name="q" id="bm-dashboard-agenda-q" class="splis-input" placeholder="Title or tracking no.">
                </div>
                <div class="w-40">
                    <label class="splis-label" for="bm-dashboard-agenda-status">Status</label>
                    <select name="status" id="bm-dashboard-agenda-status" class="splis-select">
                        <option value="">All</option>
                        @foreach (config('agenda.statuses', []) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="per_page" value="10">
            </form>
        </div>

        <div class="splis-card-header flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-700">
            <p id="bm-dashboard-agenda-search-meta" class="text-sm text-slate-500 dark:text-slate-400">Loading agenda items…</p>
            @include('partials.view-toggle', ['id' => 'bm-dashboard-agenda-view-toggle'])
        </div>

        <div id="bm-dashboard-agenda-search-results" class="transition-opacity">
            <div id="bm-dashboard-agenda-list-wrap" class="splis-table-wrap">
                <table class="splis-table">
                    <thead>
                        <tr>
                            <th>Tracking</th>
                            <th class="min-w-[12rem] max-w-md">Title</th>
                            <th class="hidden md:table-cell">Committee</th>
                            <th class="hidden sm:table-cell">Referred</th>
                            <th>Status</th>
                            <th class="w-16"></th>
                        </tr>
                    </thead>
                    <tbody id="bm-dashboard-agenda-list-body"></tbody>
                </table>
            </div>
            <div id="bm-dashboard-agenda-grid" class="hidden grid grid-cols-1 gap-4 p-4 sm:grid-cols-2"></div>
            <div id="bm-dashboard-agenda-search-pagination" class="border-t border-slate-200 p-4 dark:border-slate-700"></div>
        </div>
    </div>

    <div
        id="bm-dashboard-ob-search"
        class="splis-card"
        data-search-url="{{ route('board-member.dashboard.ob.search') }}"
    >
        <div class="splis-card-header flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="splis-card-title">Order of Business</h2>
                <p class="splis-card-subtitle">Published session documents</p>
            </div>
            <a href="{{ route('ob.sessions.index') }}" class="splis-link text-sm">All sessions</a>
        </div>

        <div class="splis-card-body border-b border-slate-200 dark:border-slate-700">
            <form id="bm-dashboard-ob-search-form" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1">
                    <label class="splis-label" for="bm-dashboard-ob-q">Search</label>
                    <input type="text" name="q" id="bm-dashboard-ob-q" class="splis-input" placeholder="Session number, venue, or type">
                </div>
            </form>
        </div>

        <div class="splis-card-header flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-700">
            <p id="bm-dashboard-ob-search-meta" class="text-sm text-slate-500 dark:text-slate-400">Loading sessions…</p>
            @include('partials.view-toggle', ['id' => 'bm-dashboard-ob-view-toggle'])
        </div>

        <div id="bm-dashboard-ob-search-results" class="transition-opacity">
            <div id="bm-dashboard-ob-list-wrap">
                <ul id="bm-dashboard-ob-list" class="divide-y divide-slate-200 dark:divide-slate-700"></ul>
            </div>
            <div id="bm-dashboard-ob-grid" class="hidden grid grid-cols-1 gap-4 p-4 sm:grid-cols-2"></div>
            <div id="bm-dashboard-ob-search-pagination" class="border-t border-slate-200 p-4 dark:border-slate-700"></div>
        </div>
    </div>
</div>
@endsection
