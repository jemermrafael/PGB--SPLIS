@extends('layouts.app')

@section('title', 'Ordinances — '.config('app.name'))

@section('content')
<div id="ordinances-search" data-search-url="{{ route('ordinances.search') }}">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Ordinances</h1>
            <p class="splis-page-subtitle">Search and browse provincial ordinances by series, subject, and enactment dates.</p>
        </div>
        @can('create', App\Models\Ordinance::class)
            <a href="{{ route('ordinances.create') }}" class="splis-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add ordinance
            </a>
        @endcan
    </div>

    <form id="ordinances-search-form" class="splis-filter-panel">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Search Ordinances</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label">Number</label>
                <input type="text" name="number" class="splis-input" placeholder="Ordinance no.">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label">Title</label>
                <input type="text" name="title" class="splis-input" placeholder="Ordinance subject">
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
                <label class="splis-label">Classification</label>
                <select name="classification" class="splis-select">
                    <option value="">All classifications</option>
                    @foreach ($classifications as $classification)
                        <option value="{{ $classification }}">{{ $classification }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Date enacted from</label>
                <input type="date" name="date_from" class="splis-input">
            </div>
            <div>
                <label class="splis-label">Date enacted to</label>
                <input type="date" name="date_to" class="splis-input">
            </div>
            <div>
                <label class="splis-label">Publication status</label>
                <select name="publication_status" class="splis-select">
                    <option value="">All publication statuses</option>
                    @foreach ($publicationStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
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
            <button type="reset" class="splis-btn-ghost">Clear filters</button>
        </div>
    </form>

    <div id="ordinances-search-results" class="transition-opacity">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p id="ordinances-search-meta" class="text-sm text-slate-500 dark:text-slate-400">Loading ordinances…</p>
            @include('partials.view-toggle', ['id' => 'ordinances-view-toggle'])
        </div>

        <div id="ordinances-list-wrap" class="splis-table-wrap" data-drag-scroll>
            <table class="splis-table">
                <thead>
                    <tr>
                        <th class="w-12">PDF</th>
                        <th>Ord. No.</th>
                        <th class="min-w-[12rem] max-w-md">Subject</th>
                        <th class="hidden lg:table-cell">Enacted</th>
                        <th class="hidden lg:table-cell">Approved</th>
                        <th class="hidden xl:table-cell">Effectivity</th>
                        <th class="hidden xl:table-cell">Board Members</th>
                        <th class="hidden sm:table-cell">Publication</th>
                    </tr>
                </thead>
                <tbody id="ordinances-list-body"></tbody>
            </table>
        </div>

        <div id="ordinances-grid" class="splis-doc-grid hidden"></div>

        <div id="ordinances-search-pagination" class="mt-6"></div>
    </div>
</div>
@endsection
