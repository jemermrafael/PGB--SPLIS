@extends('layouts.app')

@section('title', 'Staff Directory — '.config('app.name'))

@section('content')
@php
    $canManage = auth()->user()?->can('create', App\Models\DirectoryEntry::class);
@endphp
<div
    id="directory-search"
    class="w-full"
    data-search-url="{{ route('directory.search') }}"
    data-current-page="{{ $entries->currentPage() }}"
    data-last-page="{{ $entries->lastPage() }}"
    @if ($canManage) data-list-edit @endif
>
    <div class="splis-page-header">
        <x-page-heading
            title="Directory"
            subtitle="Find contact information for Provincial Government Offices and Personnel."
            icon="notebook"
            page="directory"
        />
        <div class="flex flex-wrap items-center gap-2">
            @if ($canManage)
                <button
                    type="button"
                    class="splis-btn-secondary inline-flex items-center gap-2"
                    data-list-edit-toggle
                    data-edit-label="Edit List"
                    data-done-label="Done"
                    aria-pressed="false"
                >
                    <x-icon name="edit" class="h-4 w-4" />
                    <span data-list-edit-label>Edit List</span>
                </button>
            @endif
            @can('create', App\Models\DirectoryEntry::class)
                <a href="{{ route('directory.create') }}" class="splis-btn-primary inline-flex items-center gap-2 whitespace-nowrap">
                    <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                    Add Entry
                </a>
            @endcan
        </div>
    </div>

    <form id="directory-search-form" class="splis-filter-panel mb-4" role="search">
        <label class="sr-only" for="directory-search-input">Search directory</label>
        <input
            id="directory-search-input"
            type="search"
            name="q"
            class="splis-input w-full max-w-md"
            placeholder="Search name, contact, email, designation…"
            autocomplete="off"
        />
    </form>

    <p id="directory-search-meta" class="mb-3 text-sm text-slate-500 dark:text-slate-400">
        {{ number_format($entries->total()) }} {{ Str::plural('entry', $entries->total()) }}
    </p>

    @if ($canManage)
        <div
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            data-list-edit-only
        >
            <label class="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" data-directory-select-all data-list-edit-select-all class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span>Select all</span>
                <span class="text-slate-500" data-directory-selected-count>None selected</span>
            </label>
            <form
                method="POST"
                action="{{ route('directory.bulk-destroy') }}"
                data-directory-bulk-form
                class="flex items-center gap-2"
            >
                @csrf
                @method('DELETE')
                <button type="submit" data-directory-bulk-delete class="splis-btn-danger inline-flex items-center gap-2 text-sm" disabled>
                    <x-icon name="trash" class="h-4 w-4" />
                    Delete
                </button>
            </form>
        </div>
    @endif

    <div id="directory-search-results" class="transition-opacity">
        <div class="splis-table-wrap" data-drag-scroll>
            <table class="splis-table whitespace-nowrap">
                <thead>
                    <tr>
                        @if ($canManage)
                            <th class="w-12" data-list-edit-only>
                                <span class="sr-only">Select</span>
                            </th>
                        @endif
                        <th>Name</th>
                        <th>Contact Number</th>
                        <th>Email</th>
                        <th>Designation</th>
                        <th class="text-right" data-list-edit-only>Actions</th>
                    </tr>
                </thead>
                <tbody id="directory-list-body">
                    @include('directory.partials.entries-tbody', ['entries' => $entries, 'canManage' => $canManage])
                </tbody>
            </table>
        </div>
    </div>

    <div id="directory-search-pagination" class="mt-4"></div>
</div>
@endsection
