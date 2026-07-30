@extends('layouts.app')

@section('title', 'Appropriation Ordinances — '.config('app.name'))

@section('content')
<div id="appropriation-ordinances-search" class="max-w-6xl" data-search-url="{{ route('appropriation-ordinances.search') }}">
    <div class="splis-page-header">
        <x-page-heading
            title="Appropriation Ordinances"
            subtitle="Provincial appropriation ordinances — intake through SP passage and gubernatorial approval."
            icon="ordinances"
            page="appropriation_ordinances"
        />
        @can('create', App\Models\AppropriationOrdinance::class)
            <a href="{{ route('appropriation-ordinances.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                Add Appropriation Ordinance
            </a>
        @endcan
    </div>

    <form id="appropriation-ordinances-search-form" class="splis-filter-panel mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="splis-label" for="appropriation-ordinances-filter-q">Search</label>
                <input type="text" name="q" id="appropriation-ordinances-filter-q" class="splis-input" placeholder="Number or title">
            </div>
            <div>
                <label class="splis-label" for="appropriation-ordinances-filter-series">Series year</label>
                <select name="series" id="appropriation-ordinances-filter-series" class="splis-select">
                    <option value="">All years</option>
                    @foreach ($seriesYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="splis-btn-primary">Search</button>
                <button type="reset" class="splis-btn-ghost">Clear</button>
            </div>
        </div>
    </form>

    <div id="appropriation-ordinances-search-results" class="transition-opacity">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p id="appropriation-ordinances-search-meta" class="text-sm text-slate-500 dark:text-slate-400">Loading appropriation ordinances…</p>
            @include('partials.view-toggle', ['id' => 'appropriation-ordinances-view-toggle'])
        </div>

        <div id="appropriation-ordinances-list-wrap" class="splis-table-wrap splis-card overflow-hidden" data-drag-scroll>
            <table class="splis-table">
                <thead>
                    <tr>
                        <th class="w-12">PDF</th>
                        <th>Appro. Ord. No.</th>
                        <th>Title</th>
                        <th>Date received</th>
                        <th>Date passed by SP</th>
                        <th>Date approved by Governor</th>
                    </tr>
                </thead>
                <tbody id="appropriation-ordinances-list-body"></tbody>
            </table>
        </div>

        <div id="appropriation-ordinances-grid" class="splis-doc-grid hidden"></div>

        <div id="appropriation-ordinances-search-pagination" class="mt-6"></div>
    </div>
</div>
@endsection
