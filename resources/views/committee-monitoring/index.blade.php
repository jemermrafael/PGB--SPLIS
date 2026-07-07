@extends('layouts.app')

@section('title', 'Committee Monitoring — '.config('app.name'))

@section('content')
<div class="max-w-7xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Committee Monitoring</h1>
            <p class="splis-page-subtitle">Referral tracking, committee schedules, and report/status monitoring for referred measures.</p>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($terms as $term)
            <a
                href="{{ route('committee-monitoring.index', array_filter([
                    'term' => $term->id,
                    'committee_id' => $filters['committee_id'],
                    'status' => $filters['status'],
                    'has_report' => $filters['has_report'],
                    'date_from' => $filters['date_from'],
                    'date_to' => $filters['date_to'],
                    'q' => $filters['q'],
                ])) }}"
                class="{{ $term->id === $selectedTerm->id ? 'splis-btn-primary' : 'splis-btn-secondary' }} text-sm"
            >
                {{ $term->label }}@if ($term->is_current) (current)@endif
            </a>
        @endforeach
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="splis-stat splis-stat--brand">
            <p class="splis-stat-label">Referred</p>
            <p class="splis-stat-value">{{ number_format($stats['total']) }}</p>
            <p class="splis-stat-meta">Total tracked items</p>
        </div>
        <div class="splis-stat splis-stat--gold">
            <p class="splis-stat-label">Pending</p>
            <p class="splis-stat-value">{{ number_format($stats['pending']) }}</p>
            <p class="splis-stat-meta">No outcome yet</p>
        </div>
        <div class="splis-stat splis-stat--sky">
            <p class="splis-stat-label">Scheduled</p>
            <p class="splis-stat-value">{{ number_format($stats['with_schedule']) }}</p>
            <p class="splis-stat-meta">With committee meeting date</p>
        </div>
        <div class="splis-stat splis-stat--green">
            <p class="splis-stat-label">Reports</p>
            <p class="splis-stat-value">{{ number_format($stats['with_report']) }}</p>
            <p class="splis-stat-meta">With report link</p>
        </div>
        <div class="splis-stat">
            <p class="splis-stat-label">Completed</p>
            <p class="splis-stat-value">{{ number_format($stats['completed']) }}</p>
            <p class="splis-stat-meta">With outcome</p>
        </div>
    </div>

    <form method="GET" action="{{ route('committee-monitoring.index') }}" class="splis-filter-panel">
        <input type="hidden" name="term" value="{{ $selectedTerm->id }}">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Filter committee queue</h2>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label">Committee</label>
                <select name="committee_id" class="splis-select">
                    <option value="">All committees</option>
                    @foreach ($committees as $committee)
                        <option value="{{ $committee->id }}" @selected((int) ($filters['committee_id'] ?? 0) === $committee->id)>{{ $committee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Status</label>
                <select name="status" class="splis-select">
                    <option value="">All</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                </select>
            </div>
            <div>
                <label class="splis-label">Has report</label>
                <select name="has_report" class="splis-select">
                    <option value="">All</option>
                    <option value="yes" @selected(($filters['has_report'] ?? '') === 'yes')>Yes</option>
                    <option value="no" @selected(($filters['has_report'] ?? '') === 'no')>No</option>
                </select>
            </div>
            <div>
                <label class="splis-label">Search</label>
                <input type="text" name="q" class="splis-input" value="{{ $filters['q'] ?? '' }}" placeholder="Tracking no., title, sender, outcome">
            </div>
            <div>
                <label class="splis-label">Referral date from</label>
                <input type="date" name="date_from" class="splis-input" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label class="splis-label">Referral date to</label>
                <input type="date" name="date_to" class="splis-input" value="{{ $filters['date_to'] ?? '' }}">
            </div>
        </div>

        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Apply filters</button>
            <a href="{{ route('committee-monitoring.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-ghost">Clear</a>
        </div>
    </form>

    <div class="mt-6 splis-table-wrap">
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
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="whitespace-nowrap">{{ $item->displayLabel() }}</td>
                        <td>
                            <a href="{{ route('agenda.show', $item) }}" class="splis-table-title splis-table-title--list">{{ $item->title ?: '—' }}</a>
                            <p class="text-xs text-slate-500">{{ $item->sender ?: '—' }}</p>
                        </td>
                        <td class="hidden md:table-cell">{{ $item->committee_referred ?: '—' }}</td>
                        <td class="hidden lg:table-cell whitespace-nowrap">{{ $item->date_of_referral?->format('M d, Y') ?: '—' }}</td>
                        <td class="hidden lg:table-cell whitespace-nowrap">{{ $item->date_of_committee_meeting?->format('M d, Y') ?: '—' }}</td>
                        <td>
                            @if (filled($item->committee_report_url))
                                <a href="{{ $item->committee_report_url }}" target="_blank" rel="noopener" class="splis-link">View report</a>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </td>
                        <td>
                            @if (filled($item->outcome))
                                <span class="splis-badge-linked">Completed</span>
                            @else
                                <span class="splis-badge-unlinked">Pending</span>
                            @endif
                        </td>
                        <td>{{ $item->outcome ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-10 text-center text-slate-500">No referred measures found for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</div>
@endsection

