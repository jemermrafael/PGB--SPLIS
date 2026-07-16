@extends('layouts.app')

@section('title', 'My Requests — '.config('app.name'))

@section('content')
<div
    id="municipal-request-search"
    class="splis-agenda-index max-w-6xl"
    data-search-url="{{ route('municipal.requests.search') }}"
>
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">My Requests</h1>
            <p class="splis-page-subtitle">
                @if ($municipality)
                    Requests sent by {{ $municipality->senderLabel() }} to the Sangguniang Panlalawigan.
                @else
                    Municipal requests to the Sangguniang Panlalawigan.
                @endif
            </p>
        </div>
        <a href="{{ route('dashboard') }}" class="splis-btn-ghost">Dashboard</a>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a municipality yet.</div>
    @endif

    @include('board-member.agenda.partials.expiring-soon', [
        'expiringSoonAgendas' => $expiringSoonAgendas,
        'expiringSoonDays' => $expiringSoonDays,
        'stats' => $stats,
        'requestShowRoute' => 'municipal.requests.show',
        'requestsIndexRoute' => 'municipal.requests.index',
        'expiringSubtitle' => 'Your requests approaching their due date.',
    ])

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <button type="button" class="splis-stat splis-stat--gold splis-stat--clickable text-left" data-municipal-stat-filter data-filter-status="pending">
            <p class="splis-stat-label">Pending</p>
            <p class="splis-stat-value" id="municipal-stat-pending">{{ number_format($stats['pending']) }}</p>
            <p class="splis-stat-meta">Awaiting action</p>
        </button>
        <button type="button" class="splis-stat splis-stat--amber splis-stat--clickable text-left" data-municipal-stat-filter data-filter-expiring-soon="1">
            <p class="splis-stat-label">Expiring soon</p>
            <p class="splis-stat-value" id="municipal-stat-expiring-soon">{{ number_format($stats['expiring_soon']) }}</p>
            <p class="splis-stat-meta">Within {{ $expiringSoonDays }} days</p>
        </button>
        <button type="button" class="splis-stat splis-stat--brand splis-stat--clickable text-left" data-municipal-stat-filter data-filter-due-soon="1">
            <p class="splis-stat-label">Due soon</p>
            <p class="splis-stat-value" id="municipal-stat-due-soon">{{ number_format($stats['due_soon']) }}</p>
            <p class="splis-stat-meta">Within 7 days</p>
        </button>
        <button type="button" class="splis-stat splis-stat--green splis-stat--clickable text-left" data-municipal-stat-filter data-filter-status="done">
            <p class="splis-stat-label">Accomplished</p>
            <p class="splis-stat-value" id="municipal-stat-done">{{ number_format($stats['done']) }}</p>
            <p class="splis-stat-meta">Done items</p>
        </button>
        <button type="button" class="splis-stat splis-stat--sky splis-stat--clickable text-left" data-municipal-stat-filter data-filter-status="lapsed">
            <p class="splis-stat-label">Lapsed</p>
            <p class="splis-stat-value" id="municipal-stat-lapsed">{{ number_format($stats['lapsed']) }}</p>
            <p class="splis-stat-meta">Deemed approved</p>
        </button>
    </div>

    <form id="municipal-request-search-form" class="splis-filter-panel splis-filter-panel--accent mb-6">
        <input type="hidden" name="due_soon" id="municipal-filter-due-soon" value="">
        <input type="hidden" name="expiring_soon" id="municipal-filter-expiring-soon" value="">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Search requests</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label">Tracking / Reso no.</label>
                <input type="text" name="number" class="splis-input" placeholder="001 or 014">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label">Title</label>
                <input type="text" name="title" class="splis-input" placeholder="Request title">
            </div>
            <div>
                <label class="splis-label" for="municipal-filter-status">Status</label>
                <select name="status" id="municipal-filter-status" class="splis-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Search</button>
            <button type="reset" class="splis-btn-ghost">Clear filters</button>
        </div>
    </form>

    <div id="municipal-request-search-results" class="transition-opacity">
        <p id="municipal-request-search-meta" class="mb-4 text-sm text-slate-500 dark:text-slate-400">Loading requests…</p>
        <div class="splis-table-wrap" data-drag-scroll>
            <table class="splis-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="min-w-[12rem] max-w-md">Title</th>
                        <th class="hidden sm:table-cell">Received</th>
                        <th>Due</th>
                        <th>Days left</th>
                        <th>Status</th>
                        <th class="hidden xl:table-cell">Reso no.</th>
                    </tr>
                </thead>
                <tbody id="municipal-request-list-body"></tbody>
            </table>
        </div>
        <div id="municipal-request-search-pagination" class="mt-6"></div>
    </div>
</div>
@endsection
