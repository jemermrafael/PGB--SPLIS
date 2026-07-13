@extends('layouts.app')

@section('title', 'Committee Monitoring — '.config('app.name'))

@section('content')
@php
    $activeView = $filters['view'] ?? 'referred';
@endphp
<div
    id="committee-monitoring"
    class="max-w-7xl"
    data-search-url="{{ route('committee-monitoring.index') }}"
>
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Committee Monitoring</h1>
            <p class="splis-page-subtitle">Referral tracking, committee schedules, and report/status monitoring for referred measures.</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <button
            type="button"
            data-committee-stat-filter
            data-view="referred"
            class="splis-stat splis-stat--brand splis-stat--clickable text-left {{ $activeView === 'referred' ? 'splis-stat--active' : '' }}"
        >
            <p class="splis-stat-label">Referred</p>
            <p class="splis-stat-value" id="committee-stat-total">{{ number_format($stats['total']) }}</p>
            <p class="splis-stat-meta">Total tracked items</p>
        </button>
        <button
            type="button"
            data-committee-stat-filter
            data-view="pending"
            class="splis-stat splis-stat--gold splis-stat--clickable text-left {{ $activeView === 'pending' ? 'splis-stat--active' : '' }}"
        >
            <p class="splis-stat-label">Pending</p>
            <p class="splis-stat-value" id="committee-stat-pending">{{ number_format($stats['pending']) }}</p>
            <p class="splis-stat-meta">No outcome yet</p>
        </button>
        <button
            type="button"
            data-committee-stat-filter
            data-view="scheduled"
            class="splis-stat splis-stat--sky splis-stat--clickable text-left {{ $activeView === 'scheduled' ? 'splis-stat--active' : '' }}"
        >
            <p class="splis-stat-label">Scheduled</p>
            <p class="splis-stat-value" id="committee-stat-scheduled">{{ number_format($stats['with_schedule']) }}</p>
            <p class="splis-stat-meta">With committee meeting date</p>
        </button>
        <button
            type="button"
            data-committee-stat-filter
            data-view="reports"
            class="splis-stat splis-stat--green splis-stat--clickable text-left {{ $activeView === 'reports' ? 'splis-stat--active' : '' }}"
        >
            <p class="splis-stat-label">Reports</p>
            <p class="splis-stat-value" id="committee-stat-reports">{{ number_format($stats['with_report']) }}</p>
            <p class="splis-stat-meta">With report link</p>
        </button>
        <button
            type="button"
            data-committee-stat-filter
            data-view="completed"
            class="splis-stat splis-stat--clickable text-left {{ $activeView === 'completed' ? 'splis-stat--active' : '' }}"
        >
            <p class="splis-stat-label">Completed</p>
            <p class="splis-stat-value" id="committee-stat-completed">{{ number_format($stats['completed']) }}</p>
            <p class="splis-stat-meta">With outcome</p>
        </button>
    </div>

    <form id="committee-monitoring-form" class="splis-filter-panel">
        <input type="hidden" name="view" id="committee-filter-view" value="{{ $activeView }}">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Filter committee queue</h2>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label" for="committee-filter-committee">Committee</label>
                <select name="committee_id" id="committee-filter-committee" class="splis-select">
                    <option value="">All committees</option>
                    @foreach ($committees as $committee)
                        <option value="{{ $committee->id }}" @selected((int) ($filters['committee_id'] ?? 0) === $committee->id)>{{ $committee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label" for="committee-filter-status">Status</label>
                <select name="status" id="committee-filter-status" class="splis-select">
                    <option value="">All</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending' && $activeView !== 'pending')>Pending</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed' && $activeView !== 'completed')>Completed</option>
                </select>
            </div>
            <div>
                <label class="splis-label" for="committee-filter-has-report">Has report</label>
                <select name="has_report" id="committee-filter-has-report" class="splis-select">
                    <option value="">All</option>
                    <option value="yes" @selected(($filters['has_report'] ?? '') === 'yes' && $activeView !== 'reports')>Yes</option>
                    <option value="no" @selected(($filters['has_report'] ?? '') === 'no')>No</option>
                </select>
            </div>
            <div>
                <label class="splis-label" for="committee-filter-q">Search</label>
                <input type="text" name="q" id="committee-filter-q" class="splis-input" value="{{ $filters['q'] ?? '' }}" placeholder="Tracking no., title, sender, outcome">
            </div>
            <div>
                <label class="splis-label" for="committee-filter-date-from">Referral date from</label>
                <input type="date" name="date_from" id="committee-filter-date-from" class="splis-input" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label class="splis-label" for="committee-filter-date-to">Referral date to</label>
                <input type="date" name="date_to" id="committee-filter-date-to" class="splis-input" value="{{ $filters['date_to'] ?? '' }}">
            </div>
        </div>

        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Apply filters</button>
            <button type="reset" class="splis-btn-ghost">Clear</button>
        </div>
    </form>

    <div id="committee-queue" class="mt-6">
        <p id="committee-monitoring-meta" class="mb-3 text-sm text-slate-500" aria-live="polite">Loading…</p>
        <div id="committee-monitoring-results" class="splis-table-wrap">
            <table class="splis-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="min-w-[14rem]">Title</th>
                        <th class="hidden md:table-cell">Committee</th>
                        <th class="hidden lg:table-cell">Referred</th>
                        <th class="hidden lg:table-cell">Meeting</th>
                        <th>Report</th>
                        <th>Status</th>
                        <th>Outcome</th>
                    </tr>
                </thead>
                <tbody id="committee-monitoring-list-body">
                    <tr>
                        <td colspan="8" class="py-10 text-center text-slate-500">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="committee-monitoring-pagination" class="mt-4"></div>
    </div>
</div>
@endsection
