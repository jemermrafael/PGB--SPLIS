@extends('layouts.app')

@section('title', 'Resolutions — '.config('app.name'))

@section('content')
<div id="resolutions-search" class="splis-resolutions-index" data-search-url="{{ route('resolutions.search') }}">
    <div class="splis-page-header">
        <x-page-heading
            title="All Resolutions"
            subtitle="Search and browse the adopted resolution archive."
            icon="file-text"
        />
        <div class="flex flex-wrap gap-2">
            @can('create', App\Models\Resolution::class)
                <a href="{{ route('resolutions.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                    <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                    Add Resolution
                </a>
            @endcan
        </div>
    </div>

    <form id="resolutions-search-form" class="splis-filter-panel">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Search Resolutions</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label">Number</label>
                <input type="text" name="number" class="splis-input" placeholder="Resolution no.">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label">Title</label>
                <input type="text" name="title" class="splis-input" placeholder="Resolution title">
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
        </div>

        <details id="resolutions-advanced-filters" class="splis-filter-advanced">
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
            <button type="reset" class="splis-btn-ghost">Clear filters</button>
        </div>
    </form>

    <div id="resolutions-search-results" class="transition-opacity">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p id="resolutions-search-meta" class="text-sm text-slate-500 dark:text-slate-400">Loading resolutions…</p>
            @include('partials.view-toggle', ['id' => 'resolutions-view-toggle'])
        </div>

        <div id="resolutions-list-wrap" class="splis-table-wrap" data-drag-scroll>
            <table class="splis-table">
                <thead>
                    <tr>
                        <th class="w-12">PDF</th>
                        <th>Resolution No.</th>
                        <th class="min-w-[12rem] max-w-md">Title</th>
                        <th class="hidden md:table-cell">Author</th>
                        <th class="hidden lg:table-cell">Committee</th>
                        <th class="hidden sm:table-cell">Date</th>
                        <th>Status</th>
                        <th class="hidden lg:table-cell">Series</th>
                    </tr>
                </thead>
                <tbody id="resolutions-list-body"></tbody>
            </table>
        </div>

        <div id="resolutions-grid" class="splis-doc-grid hidden"></div>

        <div id="resolutions-search-pagination" class="mt-6"></div>
    </div>
</div>
@endsection
