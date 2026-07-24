@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
<div class="splis-dashboard w-full">
    <div class="splis-dashboard-hero mb-8">
        <div class="splis-dashboard-hero-glow" aria-hidden="true"></div>
        <div class="splis-dashboard-hero-content">
            <p class="splis-dashboard-hero-eyebrow">Municipal portal</p>
            <h1 class="splis-page-title text-white">Welcome, {{ $user->name }}</h1>
            <p class="splis-dashboard-hero-subtitle">
                @if ($municipality)
                    Track the status of requests sent by {{ $municipality->senderLabel() }} to the Sangguniang Panlalawigan.
                @else
                    Track the status of your municipal requests to the Sangguniang Panlalawigan.
                @endif
            </p>
        </div>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a municipality yet. Please contact the SP office administrator.</div>
    @endif

    @include('board-member.agenda.partials.expiring-soon', [
        'expiringSoonAgendas' => $expiringSoonAgendas,
        'expiringSoonDays' => $expiringSoonDays,
        'stats' => $stats,
        'requestShowRoute' => 'municipal.requests.show',
        'requestsIndexRoute' => 'municipal.requests.index',
        'expiringSubtitle' => 'Your requests approaching their due date.',
    ])

    <div
        id="municipal-dashboard-search"
        class="splis-card"
        data-search-url="{{ route('municipal.requests.search') }}"
    >
        <div class="splis-card-header flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="splis-card-title">Your requests</h2>
                <p class="splis-card-subtitle">
                    @if ($municipality)
                        Items sent by {{ $municipality->senderLabel() }}
                    @else
                        Municipal requests
                    @endif
                </p>
            </div>
            <a href="{{ route('municipal.requests.index') }}" class="splis-link text-sm">All requests</a>
        </div>

        <div class="splis-card-body border-b border-slate-200 dark:border-slate-700">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="splis-stat splis-stat--gold text-left">
                    <p class="splis-stat-label">Pending</p>
                    <p class="splis-stat-value" id="municipal-stat-pending">{{ number_format($stats['pending']) }}</p>
                    <p class="splis-stat-meta">Awaiting action</p>
                </div>
                <div class="splis-stat splis-stat--amber text-left">
                    <p class="splis-stat-label">Expiring soon</p>
                    <p class="splis-stat-value" id="municipal-stat-expiring-soon">{{ number_format($stats['expiring_soon']) }}</p>
                    <p class="splis-stat-meta">Within {{ $expiringSoonDays }} days</p>
                </div>
                <div class="splis-stat splis-stat--brand text-left">
                    <p class="splis-stat-label">Due soon</p>
                    <p class="splis-stat-value" id="municipal-stat-due-soon">{{ number_format($stats['due_soon']) }}</p>
                    <p class="splis-stat-meta">Within 7 days</p>
                </div>
                <div class="splis-stat splis-stat--green text-left">
                    <p class="splis-stat-label">Accomplished</p>
                    <p class="splis-stat-value" id="municipal-stat-done">{{ number_format($stats['done']) }}</p>
                    <p class="splis-stat-meta">Done items</p>
                </div>
                <div class="splis-stat splis-stat--sky text-left">
                    <p class="splis-stat-label">Lapsed</p>
                    <p class="splis-stat-value" id="municipal-stat-lapsed">{{ number_format($stats['lapsed']) }}</p>
                    <p class="splis-stat-meta">Deemed approved</p>
                </div>
            </div>
        </div>

        <div class="splis-card-body border-b border-slate-200 dark:border-slate-700">
            <form id="municipal-request-search-form" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[10rem] flex-1">
                    <label class="splis-label" for="municipal-request-q">Search</label>
                    <input type="text" name="q" id="municipal-request-q" class="splis-input" placeholder="Title or tracking no.">
                </div>
                <div class="w-40">
                    <label class="splis-label" for="municipal-request-status">Status</label>
                    <select name="status" id="municipal-request-status" class="splis-select">
                        <option value="">All</option>
                        @foreach (config('agenda.statuses', []) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="per_page" value="8">
            </form>
        </div>

        <div class="splis-card-header border-b border-slate-200 dark:border-slate-700">
            <p id="municipal-request-search-meta" class="text-sm text-slate-500 dark:text-slate-400">Loading requests…</p>
        </div>

        <div id="municipal-request-search-results" class="transition-opacity">
            <div class="splis-table-wrap">
                <table class="splis-table">
                    <thead>
                        <tr>
                            <th>Tracking</th>
                            <th class="min-w-[12rem] max-w-md">Title</th>
                            <th class="hidden sm:table-cell">Received</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th class="w-16"></th>
                        </tr>
                    </thead>
                    <tbody id="municipal-request-list-body"></tbody>
                </table>
            </div>
            <div id="municipal-request-search-pagination" class="border-t border-slate-200 p-4 dark:border-slate-700"></div>
        </div>
    </div>
</div>
@endsection
