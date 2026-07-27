@extends('layouts.app')

@section('title', 'Directory Categories — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Directory Categories</h1>
            <p class="splis-page-subtitle">Group Directory entries by Office or Unit.</p>
        </div>
        <a href="{{ route('directory.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back to Directory
        </a>
    </div>

    <div class="mb-6 splis-card splis-card-body">
        <h2 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Add Category</h2>
        <form method="POST" action="{{ route('directory.categories.store') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <div class="min-w-0 flex-1">
                <label class="splis-label" for="name">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required class="splis-input" maxlength="120" placeholder="e.g. SP Secretariat">
            </div>
            <div>
                <label class="splis-label" for="sort_order">Sort</label>
                <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="splis-input w-24">
            </div>
            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="plus" class="h-4 w-4" />
                Add
            </button>
        </form>
    </div>

    <div class="splis-card overflow-hidden">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Sort</th>
                    <th>Entries</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td colspan="4" class="!p-3">
                            <form method="POST" action="{{ route('directory.categories.update', $category) }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_5rem_4rem_auto] sm:items-center">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ old('name', $category->name) }}" required class="splis-input" maxlength="120">
                                <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" class="splis-input">
                                <span class="text-sm text-slate-500 tabular-nums">{{ $category->entries_count }}</span>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="submit" class="splis-btn-secondary text-sm">Save</button>
                                    <button
                                        type="submit"
                                        form="delete-category-{{ $category->id }}"
                                        class="splis-btn-ghost text-sm text-red-600"
                                    >Delete</button>
                                </div>
                            </form>
                            <form
                                id="delete-category-{{ $category->id }}"
                                method="POST"
                                action="{{ route('directory.categories.destroy', $category) }}"
                                data-confirm-submit
                                data-confirm-title="Remove this category?"
                                data-confirm-message="Entries in this category will become uncategorized."
                                data-confirm-label="Remove"
                            >
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-sm text-slate-500">No categories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
