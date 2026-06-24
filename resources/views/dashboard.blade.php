@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
<div id="dashboard-search" data-search-url="{{ route('dashboard.documents.search') }}">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Dashboard</h1>
            <p class="splis-page-subtitle">Search and browse legislative documents — {{ number_format($totalResolutions) }} records in the archive.</p>
        </div>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="splis-stat">
            <p class="splis-stat-label">Total Documents</p>
            <p class="splis-stat-value">{{ number_format($totalResolutions) }}</p>
            <p class="splis-stat-meta">{{ number_format($legacyCount) }} imported · {{ number_format($newCount) }} new</p>
        </div>
        <div class="splis-stat">
            <p class="splis-stat-label">Current Year ({{ date('Y') }})</p>
            <p class="splis-stat-value">{{ number_format($currentYearCount) }}</p>
        </div>
        <div class="splis-stat">
            <p class="splis-stat-label">Imported Archive</p>
            <p class="splis-stat-value">{{ number_format($legacyCount) }}</p>
        </div>
        <div class="splis-stat">
            <p class="splis-stat-label">New in SPLIS</p>
            <p class="splis-stat-value">{{ number_format($newCount) }}</p>
        </div>
    </div>

    <form id="dashboard-search-form" class="splis-filter-panel">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Search documents</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label">Number</label>
                <input type="text" name="number" class="splis-input" placeholder="Resolution / ordinance no.">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label">Title</label>
                <input type="text" name="title" class="splis-input" placeholder="Document title">
            </div>
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
                <label class="splis-label">Date from</label>
                <input type="date" name="date_from" class="splis-input">
            </div>
            <div>
                <label class="splis-label">Date to</label>
                <input type="date" name="date_to" class="splis-input">
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
                    <option value="">All types</option>
                    <option value="resolution">Resolution</option>
                    <option value="ordinance">Ordinance</option>
                </select>
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
                    @foreach ($municipalities as $mun)
                        <option value="{{ $mun->id }}">{{ $mun->description }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <input type="checkbox" name="has_pdf" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Has PDF only
                </label>
            </div>
        </div>
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

        <div id="dashboard-list-wrap" class="splis-table-wrap">
            <table class="splis-table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th class="min-w-[12rem] max-w-md">Title</th>
                        <th class="hidden md:table-cell">Author</th>
                        <th class="hidden lg:table-cell">Committee</th>
                        <th class="hidden sm:table-cell">Date</th>
                        <th class="hidden xl:table-cell">Subject</th>
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

    <div class="splis-card mt-8">
        <div class="splis-card-header">
            <h2 class="splis-card-title">Recent Activity</h2>
        </div>
        <div class="splis-card-body !pt-4">
            <ul class="space-y-4">
                @forelse ($recentActivity as $log)
                    <li class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4 last:border-0 last:pb-0 dark:border-slate-700">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $log->action }}</span>
                        <span class="shrink-0 text-xs text-slate-400">{{ $log->created_at?->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-slate-400">No activity yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
