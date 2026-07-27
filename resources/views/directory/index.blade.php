@extends('layouts.app')

@section('title', 'Staff Directory — '.config('app.name'))

@section('content')
<div
    id="directory-search"
    class="w-full"
    data-search-url="{{ route('directory.search') }}"
    data-category="{{ $selectedCategoryId ?? '' }}"
    data-current-page="{{ $entries->currentPage() }}"
    data-last-page="{{ $entries->lastPage() }}"
>
    <div class="splis-page-header">
        <x-page-heading
            title="Directory"
            subtitle="Find contact information for Provincial Government Offices and Personnel."
            icon="notebook"
            page="directory"
        />
        <div class="flex flex-wrap gap-2">
            @can('create', App\Models\DirectoryEntry::class)
                <a href="{{ route('directory.categories.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                    Manage Categories
                </a>
                <a href="{{ route('directory.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                    <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                    Add Entry
                </a>
            @endcan
        </div>
    </div>

    @if ($categories->isNotEmpty())
        <div class="mb-4 flex flex-wrap gap-2">
            <a
                href="{{ route('directory.index') }}"
                @class([
                    'splis-btn-secondary text-sm',
                    'ring-2 ring-brand-200' => ! $selectedCategoryId,
                ])
            >All</a>
            @foreach ($categories as $category)
                <a
                    href="{{ route('directory.index', ['category' => $category->id]) }}"
                    @class([
                        'splis-btn-secondary text-sm',
                        'ring-2 ring-brand-200' => (int) $selectedCategoryId === (int) $category->id,
                    ])
                >{{ $category->name }}</a>
            @endforeach
        </div>
    @endif

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

    <div id="directory-search-results" class="transition-opacity">
        <div class="splis-table-wrap" data-drag-scroll>
            <table class="splis-table whitespace-nowrap">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Contact Number</th>
                        <th>Email</th>
                        <th>Focal persons</th>
                        <th>Designation</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="directory-list-body">
                    @include('directory.partials.entries-tbody', ['entries' => $entries])
                </tbody>
            </table>
        </div>
    </div>

    <div id="directory-search-pagination" class="mt-4"></div>
</div>
@endsection
