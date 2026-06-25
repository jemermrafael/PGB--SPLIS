@extends('layouts.app')

@section('title', 'Incoming — '.config('app.name'))

@section('content')
<div id="incoming-search" data-search-url="{{ route('incoming.search') }}">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Incoming</h1>
            <p class="splis-page-subtitle">Track municipal submissions and workflow before adoption into the resolution archive.</p>
        </div>
        @can('create', App\Models\IncomingDocument::class)
            <a href="{{ route('incoming.create') }}" class="splis-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add Incoming
            </a>
        @endcan
    </div>

    <form id="incoming-search-form" class="splis-filter-panel">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Search incoming</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label">Number</label>
                <input type="text" name="number" class="splis-input" placeholder="SP or municipal resolution no.">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label">Title</label>
                <input type="text" name="title" class="splis-input" placeholder="Resolution title">
            </div>
            <div>
                <label class="splis-label">Committee</label>
                <input type="text" name="committee" class="splis-input" placeholder="Referral / committee">
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
            <div>
                <label class="splis-label">Link status</label>
                <select name="link_status" class="splis-select">
                    <option value="">All</option>
                    <option value="unlinked">Unlinked</option>
                    <option value="linked">Linked</option>
                </select>
            </div>
            <div>
                <label class="splis-label">Source</label>
                <select name="source" class="splis-select">
                    <option value="">All</option>
                    <option value="sptrack">sptrack import</option>
                    <option value="manual">Manual</option>
                </select>
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <input type="checkbox" name="has_pdf" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Has PDF URL only
                </label>
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Search</button>
            <button type="reset" class="splis-btn-ghost">Clear filters</button>
        </div>
    </form>

    <div id="incoming-search-results" class="transition-opacity">
        <p id="incoming-search-meta" class="mb-4 text-sm text-slate-500 dark:text-slate-400">Loading incoming documents…</p>

        <div class="splis-table-wrap">
            <table class="splis-table">
                <thead>
                    <tr>
                        <th>File / Mun. Res.</th>
                        <th class="min-w-[12rem] max-w-md">Title</th>
                        <th class="hidden md:table-cell">Municipality</th>
                        <th class="hidden lg:table-cell">Committee</th>
                        <th class="hidden sm:table-cell">Date</th>
                        <th>SP No.</th>
                        <th>Link</th>
                        <th class="hidden xl:table-cell">Source</th>
                    </tr>
                </thead>
                <tbody id="incoming-list-body"></tbody>
            </table>
        </div>

        <div id="incoming-search-pagination" class="mt-6"></div>
    </div>
</div>
@endsection
