@extends('layouts.app')

@section('title', 'Board Members — '.config('app.name'))

@section('content')
@php
    $canManage = auth()->user()?->can('create', App\Models\BoardMember::class);
@endphp
<div id="board-members-index" class="max-w-6xl">
    <div class="splis-page-header">
        <x-page-heading
            title="Board Members"
            subtitle="Sangguniang Panlalawigan roster — Vice Governor, District Board Members, and Ex Officio Members."
            icon="users"
        />
        @can('create', App\Models\BoardMember::class)
            <a href="{{ route('board-members.create', ['term' => $selectedTerm->id]) }}" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                Add Board Member
            </a>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('committees.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="eye" class="h-4 w-4" />
            Committees
        </a>
        <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="eye" class="h-4 w-4" />
            Election Terms
        </a>
    </div>

    @include('partials.term-switcher', [
        'terms' => $terms,
        'selectedTerm' => $selectedTerm,
        'routeName' => 'board-members.index',
    ])

    @if ($canManage && $boardMembersByDistrict->isNotEmpty())
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <label class="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" data-board-member-select-all class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span>Select all</span>
                <span class="text-slate-500" data-board-member-selected-count>None selected</span>
            </label>
            <form
                method="POST"
                action="{{ route('board-members.bulk-destroy') }}"
                data-board-member-bulk-form
                class="flex items-center gap-2"
            >
                @csrf
                @method('DELETE')
                <input type="hidden" name="term" value="{{ $selectedTerm->id }}">
                <button type="submit" data-board-member-bulk-delete class="splis-btn-danger inline-flex items-center gap-2 text-sm" disabled>
                    <x-icon name="trash" class="h-4 w-4" />
                    Delete
                </button>
            </form>
        </div>
    @endif

    @forelse ($boardMembersByDistrict as $district => $assignments)
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $district }}</h2>
            <div class="splis-table-wrap">
                <table class="splis-table">
                    <thead>
                        <tr>
                            @if ($canManage)
                                <th class="w-12">
                                    <span class="sr-only">Select</span>
                                </th>
                            @endif
                            <th>Name</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignments as $assignment)
                            @php $member = $assignment->boardMember; @endphp
                            @if ($member)
                                <tr>
                                    @if ($canManage)
                                        <td>
                                            @can('delete', $member)
                                                <input
                                                    type="checkbox"
                                                    value="{{ $member->id }}"
                                                    data-board-member-checkbox
                                                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                                    aria-label="Select {{ $member->displayName() }}"
                                                >
                                            @endcan
                                        </td>
                                    @endif
                                    <td class="font-medium text-slate-900 dark:text-slate-100">
                                        <a href="{{ route('board-members.show', ['boardMember' => $member, 'term' => $selectedTerm->id]) }}" class="hover:text-brand-700 dark:hover:text-brand-300">
                                            {{ $member->displayName() }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($assignment->is_active)
                                            <span class="splis-badge-linked">Active</span>
                                        @else
                                            <span class="splis-badge-unlinked">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('board-members.show', ['boardMember' => $member, 'term' => $selectedTerm->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-sm">
                                                <x-icon name="eye" class="h-4 w-4" />
                                                Profile
                                            </a>
                                            @can('update', $member)
                                                <a href="{{ route('board-members.edit', ['boardMember' => $member, 'term' => $selectedTerm->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-sm">
                                                    <x-icon name="edit" class="h-4 w-4" />
                                                    Edit
                                                </a>
                                            @endcan
                                            @can('delete', $member)
                                                <form
                                                    method="POST"
                                                    action="{{ route('board-members.destroy', $member) }}"
                                                    data-confirm-submit
                                                    data-confirm-title="Move Board Member to trash?"
                                                    data-confirm-message="Move {{ $member->displayName() }} to trash? Superadmin can restore from Trash."
                                                    data-confirm-label="Delete"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="term" value="{{ $selectedTerm->id }}">
                                                    <button type="submit" class="splis-btn-danger inline-flex items-center gap-2 text-sm">
                                                        <x-icon name="trash" class="h-4 w-4" />
                                                        Delete
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="splis-card splis-card-body py-10 text-center text-slate-500">
            No Board Members for {{ $selectedTerm->label }} yet. Add personnel for this term or switch to another election period.
        </div>
    @endforelse
</div>
@endsection
