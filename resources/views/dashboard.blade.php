@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
<div id="dashboard-search" class="splis-dashboard w-full" data-search-url="{{ route('dashboard.documents.search') }}">
    <div class="splis-dashboard-hero">
        <div class="splis-dashboard-hero-glow" aria-hidden="true"></div>
        <div class="splis-dashboard-hero-building" aria-hidden="true"></div>
        <div class="splis-dashboard-hero-content">
            <p class="splis-dashboard-hero-eyebrow">Legislative archive</p>
            <h1 class="splis-page-title text-white">Welcome, {{ auth()->user()->name }}</h1>
            <p class="splis-dashboard-hero-subtitle">Search Resolutions and Ordinances — {{ number_format($totalDocuments) }} documents in the archive ({{ number_format($totalResolutions) }} Resolutions · {{ number_format($totalOrdinances) }} Ordinances)</p>
        </div>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="splis-stat splis-stat--brand">
            <div class="splis-stat-icon splis-stat-icon--brand">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            </div>
            <p class="splis-stat-label">Total Documents</p>
            <p class="splis-stat-value">{{ number_format($totalDocuments) }}</p>
            <p class="splis-stat-meta">{{ number_format($totalResolutions) }} Resolutions · {{ number_format($totalOrdinances) }} Ordinances</p>
        </div>
        <div class="splis-stat splis-stat--gold">
            <div class="splis-stat-icon splis-stat-icon--gold">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
            </div>
            <p class="splis-stat-label">Total Resolutions</p>
            <p class="splis-stat-value">{{ number_format($totalResolutions) }}</p>
        </div>
        <div class="splis-stat splis-stat--green">
            <div class="splis-stat-icon splis-stat-icon--green">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5a1.125 1.125 0 00-1.125-1.125H3.375a1.125 1.125 0 00-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
            </div>
            <p class="splis-stat-label">Total Ordinances</p>
            <p class="splis-stat-value">{{ number_format($totalOrdinances) }}</p>
        </div>
        <div class="splis-stat splis-stat--sky">
            <div class="splis-stat-icon splis-stat-icon--sky">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            </div>
            <p class="splis-stat-label">Current Year ({{ date('Y') }})</p>
            <p class="splis-stat-value">{{ number_format($currentYearCount) }}</p>
            <p class="splis-stat-meta">Resolutions + Ordinances</p>
        </div>
    </div>

    <form id="dashboard-search-form" class="splis-filter-panel splis-filter-panel--accent">
        <div class="splis-filter-panel-accent-bar" aria-hidden="true"></div>
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
            <span class="splis-filter-panel-icon">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            </span>
            Search Documents
        </h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label">Number</label>
                <input type="text" name="number" class="splis-input" placeholder="Resolution No. or Ordinance No.">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label">Title</label>
                <input type="text" name="title" class="splis-input" placeholder="Document title">
            </div>
            <div>
                <label class="splis-label">Series (Year)</label>
                <select name="series" class="splis-select">
                    <option value="">All years</option>
                    @foreach ($seriesYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Document type</label>
                <select name="document_type" class="splis-select">
                    <option value="">All document types</option>
                    <option value="resolution">Resolutions only</option>
                    <option value="ordinance">Ordinances only</option>
                </select>
            </div>
        </div>

        <details id="dashboard-advanced-filters" class="splis-filter-advanced">
            <summary class="splis-filter-advanced-toggle">
                <span>Advanced search</span>
                <svg class="splis-filter-advanced-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </summary>
            <div class="splis-filter-advanced-panel">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="splis-label">Author / Sponsor</label>
                        <input type="text" name="author" class="splis-input" placeholder="Sponsored by">
                    </div>
                    <div>
                        <label class="splis-label">Committee</label>
                        <input type="text" name="committee" class="splis-input" placeholder="Committee">
                    </div>
                    <div>
                        <label class="splis-label">Keywords</label>
                        <input type="text" name="keyword" class="splis-input" placeholder="Keywords">
                    </div>
                    <div>
                        <label class="splis-label">Status</label>
                        <select name="status" class="splis-select">
                            <option value="">All statuses</option>
                            <option value="approved">Approved</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div>
                        <label class="splis-label">Date from</label>
                        <input type="date" name="date_from" class="splis-input">
                    </div>
                    <div>
                        <label class="splis-label">Date to</label>
                        <input type="date" name="date_to" class="splis-input">
                    </div>
                    <div>
                        <label class="splis-label">Subject / Category</label>
                        <select name="category_id" class="splis-select">
                            <option value="">All categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="splis-label">Department</label>
                        <select name="department_id" class="splis-select">
                            <option value="">All departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="splis-label">Municipality</label>
                        <select name="municipality_id" class="splis-select">
                            <option value="">All municipalities</option>
                            <option value="bataan">Bataan</option>
                            @foreach ($municipalities as $mun)
                                <option value="{{ $mun->id }}">{{ $mun->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="splis-filter-check">
                            <input type="checkbox" name="has_pdf" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            Has PDF only
                        </label>
                    </div>
                </div>
            </div>
        </details>

        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Search</button>
            <button type="reset" class="splis-btn-ghost" id="dashboard-search-reset">Clear filters</button>
        </div>
    </form>

    <div id="dashboard-search-results" class="transition-opacity">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p id="dashboard-search-meta" class="text-sm text-slate-500 dark:text-slate-400">Loading documents…</p>
            @include('partials.view-toggle', ['id' => 'dashboard-view-toggle'])
        </div>

        <div id="dashboard-list-wrap" class="splis-table-wrap" data-drag-scroll>
            <table class="splis-table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th class="hidden sm:table-cell">Type</th>
                        <th class="min-w-[12rem] max-w-md">Title</th>
                        <th class="hidden md:table-cell">Author</th>
                        <th class="hidden lg:table-cell">Committee</th>
                        <th class="hidden sm:table-cell">Date</th>
                        <th class="hidden xl:table-cell">Publication</th>
                        <th>Status</th>
                        <th class="w-12">PDF</th>
                    </tr>
                </thead>
                <tbody id="dashboard-list-body"></tbody>
            </table>
        </div>

        <div id="dashboard-grid" class="splis-doc-grid hidden"></div>

        <div id="dashboard-search-pagination" class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"></div>
    </div>

    @if (auth()->user()->canAdmin())
    <div class="splis-card splis-card--accent mt-8">
        <div class="splis-card-header splis-card-header--accent">
            <h2 class="splis-card-title flex items-center gap-2">
                <span class="splis-card-title-icon splis-card-title-icon--activity">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                Recent Activity
            </h2>
        </div>
        <div class="splis-card-body !pt-4">
            @php
                $activityTones = [
                    'incoming.created' => 'blue',
                    'incoming.updated' => 'amber',
                    'incoming.linked' => 'green',
                    'incoming.imported_from_sptrack' => 'slate',
                    'resolution.created' => 'brand',
                    'resolution.updated' => 'amber',
                    'resolution.trashed' => 'amber',
                    'resolution.restored' => 'green',
                    'resolution.deleted' => 'red',
                    'resolution.published_from_incoming' => 'gold',
                    'agenda.created' => 'blue',
                    'agenda.published' => 'green',
                    'ordinance.created' => 'brand',
                ];
            @endphp
            <ul class="space-y-3">
                @forelse ($recentActivity as $log)
                    @php
                        $tone = $activityTones[$log->action] ?? 'slate';
                        $label = \App\Support\ActivityLogPresenter::label($log);
                    @endphp
                    <li class="splis-activity-feed-item splis-activity-feed-item--{{ $tone }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <span class="splis-activity-pill splis-activity-pill--{{ $tone }}">{{ $label }}</span>
                                @if ($log->user)
                                    <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-300">{{ $log->user->name }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-start gap-2">
                                <span class="text-xs text-slate-400">{{ $log->created_at?->diffForHumans() }}</span>
                                @include('partials.activity-log-delete', ['log' => $log])
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-slate-400">No activity yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
    @endif
</div>
@endsection
