@extends('layouts.app')

@section('title', 'All Ordinances — '.config('app.name'))

@section('content')
<div class="max-w-6xl" id="bm-all-ordinances" data-search-url="{{ route('board-member.ordinances.all.search') }}">
    <div class="splis-page-header">
        <x-page-heading
            title="All Ordinances"
            subtitle="Provincial Ordinances and Appropriation Ordinances in one list."
            icon="ordinances"
        />
    </div>

    <form method="GET" id="bm-ordinances-search-form" class="splis-filter-panel mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="splis-label" for="q">Search</label>
                <input type="text" name="q" id="q" value="{{ request('q') }}" class="splis-input" placeholder="Number or title">
            </div>
            <div>
                <label class="splis-label" for="series">Series Year</label>
                <select name="series" id="series" class="splis-select">
                    <option value="">All years</option>
                    @foreach ($seriesYears as $year)
                        <option value="{{ $year }}" @selected((string) request('series') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="splis-btn-primary">Search</button>
                <a href="{{ route('board-member.ordinances.all') }}" class="splis-btn-ghost">Clear</a>
            </div>
        </div>
        <details id="bm-ordinances-advanced-filters" class="splis-filter-advanced mt-4">
            <summary class="splis-filter-advanced-toggle">
                <span>Advanced search</span>
                <svg class="splis-filter-advanced-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </summary>
            <div class="splis-filter-advanced-panel">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="splis-label" for="bm-type">Type</label>
                        <select name="type" id="bm-type" class="splis-select">
                            <option value="">All types</option>
                            <option value="ordinance">Ordinance</option>
                            <option value="appropriation_ordinance">Appropriation Ordinance</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="splis-filter-check">
                            <input type="checkbox" name="has_authors" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            Has Board Member attribution
                        </label>
                    </div>
                </div>
            </div>
        </details>
    </form>

    <p id="bm-ordinances-meta" class="mb-3 text-sm text-slate-500 dark:text-slate-400">Loading ordinances…</p>
    <div id="bm-ordinances-results">
        @include('board-member.ordinances.partials.table', [
            'records' => $records,
            'showType' => false,
            'emptyMessage' => 'No ordinances found.',
            'showPagination' => false,
        ])
    </div>
    <div id="bm-ordinances-pagination" class="mt-6"></div>
</div>
@endsection
