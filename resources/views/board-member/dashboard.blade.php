@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
<div class="splis-dashboard max-w-6xl">
    <div class="splis-dashboard-hero mb-8">
        <div class="splis-dashboard-hero-glow" aria-hidden="true"></div>
        <div class="relative">
            <p class="splis-dashboard-hero-eyebrow">Board Member Portal</p>
            <h1 class="splis-page-title text-white">Welcome, Hon. {{ $user->name }}</h1>
            <p class="splis-dashboard-hero-subtitle">
                Today’s briefing, Committee Agenda, and Order of Business.
            </p>
        </div>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a Board Member profile yet. Please contact the SP office administrator so your login stays connected to the legislative roster.</div>
    @else
        @include('board-member.dashboard.partials.briefing', [
            'briefing' => $briefing,
            'expiringSoonDays' => $expiringSoonDays,
        ])
    @endif

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
            <a href="{{ route('board-member.agenda.index') }}" class="splis-link text-sm">My Agenda</a>
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
                <p class="splis-card-subtitle">Scheduled sessions — with items from your committees, View OB, and calendar download</p>
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
