@extends('layouts.app')

@section('title', 'Archives — '.config('app.name'))

@section('content')
<div
    id="archives-search"
    class="max-w-6xl"
    data-search-url="{{ route('admin.archives.search') }}"
    data-restore-url-template="{{ url('/agenda') }}/__ID__/restore-archive"
>
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Archives</h1>
            <p class="splis-page-subtitle">Archived agendas are hidden from the main Agenda list and can be restored anytime.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex flex-wrap gap-2">
        <span class="splis-btn-secondary !px-3 !py-1.5 text-sm ring-2 ring-brand-600">
            Agenda
            <span id="archives-count" class="ml-1 tabular-nums opacity-70">({{ number_format($archivedCount) }})</span>
        </span>
    </div>

    <form id="archives-search-form" class="splis-card mb-6 p-4 sm:p-5" role="search">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="archive-number" class="splis-label">Number</label>
                <input id="archive-number" type="text" name="number" class="splis-input mt-1" placeholder="Tracking / Reso No." autocomplete="off">
            </div>
            <div class="sm:col-span-2">
                <label for="archive-title" class="splis-label">Title</label>
                <input id="archive-title" type="text" name="title" class="splis-input mt-1" autocomplete="off">
            </div>
            <div>
                <label for="archive-committee" class="splis-label">Committee</label>
                <select id="archive-committee" name="committee" class="splis-input mt-1">
                    <option value="">All</option>
                    @foreach ($committees as $committee)
                        <option value="{{ $committee }}">{{ $committee }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="archive-status" class="splis-label">Status</label>
                <select id="archive-status" name="status" class="splis-input mt-1">
                    <option value="">All</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="splis-btn-primary">Search</button>
            <button type="reset" class="splis-btn-ghost">Clear</button>
            <p id="archives-search-meta" class="ml-auto text-sm text-slate-500 dark:text-slate-400">Loading archived agendas…</p>
        </div>
    </form>

    <div id="archives-search-results" class="transition-opacity">
        <div class="splis-table-wrap" data-drag-scroll>
            <table class="splis-table">
                <thead>
                    <tr>
                        <th>Agenda</th>
                        <th class="hidden md:table-cell">Committee</th>
                        <th class="hidden lg:table-cell">Status</th>
                        <th>Archived</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="archives-list-body"></tbody>
            </table>
        </div>
        <div id="archives-search-pagination" class="mt-4"></div>
    </div>
</div>
@endsection
