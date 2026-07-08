@extends('layouts.app')

@section('title', $committee->name.' — My Agenda — '.config('app.name'))

@section('content')
<div
    id="bm-agenda-search"
    class="splis-agenda-index max-w-6xl"
    data-search-url="{{ route('board-member.agenda.search') }}"
    data-committee-id="{{ $committee->id }}"
>
    <div class="splis-page-header">
        <div>
            <p class="mb-1 text-sm text-slate-500">
                <a href="{{ route('board-member.agenda.index') }}" class="splis-link">My Agenda</a>
                <span class="mx-1">/</span>
                <span>{{ $committee->name }}</span>
            </p>
            <h1 class="splis-page-title">{{ $committee->name }}</h1>
            <p class="splis-page-subtitle">Your role: {{ $roleLabel }}</p>
        </div>
        <a href="{{ route('dashboard') }}" class="splis-btn-ghost">Dashboard</a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="splis-stat splis-stat--gold text-left">
            <p class="splis-stat-label">Pending</p>
            <p class="splis-stat-value" id="bm-agenda-stat-pending">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="splis-stat splis-stat--brand text-left">
            <p class="splis-stat-label">Due soon</p>
            <p class="splis-stat-value" id="bm-agenda-stat-due-soon">{{ number_format($stats['due_soon']) }}</p>
        </div>
        <div class="splis-stat splis-stat--green text-left">
            <p class="splis-stat-label">Accomplished</p>
            <p class="splis-stat-value" id="bm-agenda-stat-done">{{ number_format($stats['done']) }}</p>
        </div>
        <div class="splis-stat splis-stat--sky text-left">
            <p class="splis-stat-label">Lapsed</p>
            <p class="splis-stat-value" id="bm-agenda-stat-lapsed">{{ number_format($stats['lapsed']) }}</p>
        </div>
    </div>

    <form id="bm-agenda-search-form" class="splis-filter-panel splis-filter-panel--accent mb-6">
        <input type="hidden" name="committee_id" value="{{ $committee->id }}">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Search committee agenda</h2>
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
        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Search</button>
            <button type="reset" class="splis-btn-ghost">Clear</button>
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
                        <th class="hidden sm:table-cell">Received</th>
                        <th>Due</th>
                        <th>Days left</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="bm-agenda-list-body"></tbody>
            </table>
        </div>
        <div id="bm-agenda-search-pagination" class="mt-6"></div>
    </div>
</div>
@endsection
