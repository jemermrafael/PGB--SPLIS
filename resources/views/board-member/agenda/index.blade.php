@extends('layouts.app')

@section('title', 'My Agenda — '.config('app.name'))

@section('content')
<div
    id="bm-agenda-search"
    class="splis-agenda-index max-w-6xl"
    data-search-url="{{ route('board-member.agenda.search') }}"
>
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">My Agenda</h1>
            <p class="splis-page-subtitle">Agenda items referred to your committees.</p>
        </div>
        <a href="{{ route('agenda.index') }}" class="splis-btn-ghost">All agenda</a>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a board member profile yet.</div>
    @endif

    @include('board-member.agenda.partials.expiring-soon', [
        'expiringSoonAgendas' => $expiringSoonAgendas,
        'expiringSoonDays' => $expiringSoonDays,
        'stats' => $stats,
    ])

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <button type="button" class="splis-stat splis-stat--gold splis-stat--clickable text-left" data-bm-agenda-stat-filter data-filter-status="pending">
            <p class="splis-stat-label">Pending</p>
            <p class="splis-stat-value" id="bm-agenda-stat-pending">{{ number_format($stats['pending']) }}</p>
            <p class="splis-stat-meta">Awaiting action</p>
        </button>
        <button type="button" class="splis-stat splis-stat--amber splis-stat--clickable text-left" data-bm-agenda-stat-filter data-filter-expiring-soon="1">
            <p class="splis-stat-label">Expiring soon</p>
            <p class="splis-stat-value" id="bm-agenda-stat-expiring-soon">{{ number_format($stats['expiring_soon']) }}</p>
            <p class="splis-stat-meta">Within {{ $expiringSoonDays }} days</p>
        </button>
        <button type="button" class="splis-stat splis-stat--brand splis-stat--clickable text-left" data-bm-agenda-stat-filter data-filter-due-soon="1">
            <p class="splis-stat-label">Due soon</p>
            <p class="splis-stat-value" id="bm-agenda-stat-due-soon">{{ number_format($stats['due_soon']) }}</p>
            <p class="splis-stat-meta">Within 7 days</p>
        </button>
        <button type="button" class="splis-stat splis-stat--green splis-stat--clickable text-left" data-bm-agenda-stat-filter data-filter-status="done">
            <p class="splis-stat-label">Accomplished</p>
            <p class="splis-stat-value" id="bm-agenda-stat-done">{{ number_format($stats['done']) }}</p>
            <p class="splis-stat-meta">Done items</p>
        </button>
        <button type="button" class="splis-stat splis-stat--sky splis-stat--clickable text-left" data-bm-agenda-stat-filter data-filter-status="lapsed">
            <p class="splis-stat-label">Lapsed</p>
            <p class="splis-stat-value" id="bm-agenda-stat-lapsed">{{ number_format($stats['lapsed']) }}</p>
            <p class="splis-stat-meta">Deemed approved</p>
        </button>
    </div>

    <form id="bm-agenda-search-form" class="splis-filter-panel splis-filter-panel--accent mb-6">
        <div class="splis-filter-panel-accent-bar" aria-hidden="true"></div>
        <input type="hidden" name="due_soon" id="bm-agenda-filter-due-soon" value="">
        <input type="hidden" name="expiring_soon" id="bm-agenda-filter-expiring-soon" value="">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
            <span class="splis-filter-panel-icon">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            </span>
            Search Agenda
        </h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label">Tracking / Reso no.</label>
                <input type="text" name="number" class="splis-input" placeholder="001 or 014">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label">Title</label>
                <input type="text" name="title" class="splis-input" placeholder="Request or resolution title">
            </div>
            <div>
                <label class="splis-label" for="bm-agenda-filter-status">Status</label>
                <select name="status" id="bm-agenda-filter-status" class="splis-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <details class="splis-filter-advanced">
            <summary class="splis-filter-advanced-toggle">
                <span>Advanced search</span>
                <svg class="splis-filter-advanced-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </summary>
            <div class="splis-filter-advanced-panel">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="splis-combobox" data-combobox data-options='@json($senders)'>
                        <label class="splis-label" for="bm-agenda-filter-sender">Sender</label>
                        <div class="splis-combobox-control">
                            <input type="text" name="sender" id="bm-agenda-filter-sender" class="splis-input splis-combobox-input" placeholder="Municipality or office" autocomplete="off" data-combobox-input>
                            <button type="button" class="splis-combobox-trigger" data-combobox-trigger aria-label="Show sender options">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                            </button>
                            <div class="splis-combobox-panel" data-combobox-panel>
                                <div class="splis-combobox-list" data-combobox-list role="listbox" aria-label="Sender options"></div>
                            </div>
                        </div>
                    </div>
                    <div class="splis-combobox" data-combobox data-options='@json($committees)'>
                        <label class="splis-label" for="bm-agenda-filter-committee">Committee</label>
                        <div class="splis-combobox-control">
                            <input type="text" name="committee" id="bm-agenda-filter-committee" class="splis-input splis-combobox-input" placeholder="Committee referred" autocomplete="off" data-combobox-input>
                            <button type="button" class="splis-combobox-trigger" data-combobox-trigger aria-label="Show committee options">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                            </button>
                            <div class="splis-combobox-panel" data-combobox-panel>
                                <div class="splis-combobox-list" data-combobox-list role="listbox" aria-label="Committee options"></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="splis-label">Date from</label>
                        <input type="date" name="date_from" class="splis-input">
                    </div>
                    <div>
                        <label class="splis-label">Date to</label>
                        <input type="date" name="date_to" class="splis-input">
                    </div>
                </div>
            </div>
        </details>

        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Search</button>
            <button type="reset" class="splis-btn-ghost">Clear filters</button>
        </div>
    </form>

    <div id="bm-agenda-search-results" class="transition-opacity">
        <p id="bm-agenda-search-meta" class="mb-4 text-sm text-slate-500 dark:text-slate-400">Loading agenda items…</p>
        <div class="splis-table-wrap">
            <table class="splis-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="min-w-[12rem] max-w-md">Title</th>
                        <th class="hidden md:table-cell">Sender</th>
                        <th class="hidden lg:table-cell">Committee</th>
                        <th class="hidden sm:table-cell">Received</th>
                        <th>Due</th>
                        <th>Days left</th>
                        <th>Status</th>
                        <th class="hidden xl:table-cell">Reso no.</th>
                    </tr>
                </thead>
                <tbody id="bm-agenda-list-body"></tbody>
            </table>
        </div>
        <div id="bm-agenda-search-pagination" class="mt-6"></div>
    </div>
</div>
@endsection
