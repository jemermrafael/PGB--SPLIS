@extends('layouts.app')

@php
    $district = $assignment?->district;
    $hasAssignments = collect($roles)->flatten()->isNotEmpty();
@endphp

@section('title', $boardMember->displayName().' — Board Members — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header">
        <div class="flex min-w-0 items-start gap-4">
            @if ($boardMember->photo_path)
                <button
                    type="button"
                    class="splis-bm-photo-thumb"
                    data-pdf-modal-open
                    data-pdf-viewer="image"
                    data-pdf-src="{{ route('board-members.photo', $boardMember) }}"
                    data-pdf-url="{{ route('board-members.photo', $boardMember) }}"
                    data-pdf-title="{{ $boardMember->displayName() }}"
                    aria-label="View full profile photo"
                >
                    <img
                        src="{{ route('board-members.photo', $boardMember) }}"
                        alt="{{ $boardMember->displayName() }}"
                    >
                </button>
            @endif
            <div class="min-w-0">
                <p class="text-sm text-slate-500">
                    {{ $district ?: 'Board Member' }}
                    @if ($district === 'Ex Officio' && filled($assignment?->ex_officio_title))
                        · {{ $assignment->ex_officio_title }}
                    @endif
                </p>
                <h1 class="splis-page-title">{{ $boardMember->displayName() }}</h1>
                <p class="splis-page-subtitle">Committee assignments for the selected election term.</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $boardMember)
                <a href="{{ route('board-members.edit', ['boardMember' => $boardMember, 'term' => $selectedTerm->id]) }}" class="splis-btn-primary inline-flex items-center gap-2">
                    <x-icon name="edit" class="h-4 w-4" />
                    Edit profile
                </a>
            @endcan
            @can('delete', $boardMember)
                <form
                    method="POST"
                    action="{{ route('board-members.destroy', $boardMember) }}"
                    data-confirm-submit
                    data-confirm-title="Move Board Member to trash?"
                    data-confirm-message="Move {{ $boardMember->displayName() }} to trash? Superadmin can restore from Trash."
                    data-confirm-label="Delete"
                >
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="term" value="{{ $selectedTerm->id }}">
                    <button type="submit" class="splis-btn-danger inline-flex items-center gap-2">
                        <x-icon name="trash" class="h-4 w-4" />
                        Delete
                    </button>
                </form>
            @endcan
        </div>
    </div>

    @include('partials.term-switcher', [
        'terms' => $terms,
        'selectedTerm' => $selectedTerm,
        'routeName' => 'board-members.show',
        'routeParams' => ['boardMember' => $boardMember],
    ])

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($assignment)
            @if ($district === 'Vice Governor')
                <span class="splis-badge-linked">Presiding Officer of the Sangguniang Panlalawigan</span>
            @endif
        @else
            <span class="splis-badge-unlinked">Not on {{ $selectedTerm->label }} roster</span>
        @endif
    </div>

    <div class="splis-card mb-6 overflow-hidden">
        <div class="splis-card-header splis-card-header--emphasis">
            <h2 class="splis-card-title">Contact</h2>
        </div>
        <div class="splis-card-body">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact number</p>
                <p class="mt-1 text-slate-900 dark:text-slate-100">
                    {{ $boardMember->contactNumber() ?: '—' }}
                </p>
                @if ($boardMember->hasLinkedAccount())
                    <p class="mt-1 text-xs text-slate-500">From Board Member account</p>
                @endif
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email address</p>
                <p class="mt-1 text-slate-900 dark:text-slate-100">
                    @if ($boardMember->contactEmail())
                        <a href="mailto:{{ $boardMember->contactEmail() }}" class="splis-link break-all">{{ $boardMember->contactEmail() }}</a>
                    @else
                        —
                    @endif
                </p>
                @if ($boardMember->hasLinkedAccount())
                    <p class="mt-1 text-xs text-slate-500">From Board Member account</p>
                @endif
            </div>
        </div>
        </div>
    </div>

    <div class="splis-card mb-8 overflow-hidden">
        <div class="splis-card-header splis-card-header--emphasis">
            <h2 class="splis-card-title">Committee Assignments</h2>
            <p class="splis-card-subtitle">{{ $selectedTerm->label }}{{ $selectedTerm->is_current ? ' · Current' : '' }}</p>
        </div>
        <div class="splis-card-body space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Election term</p>
            <p class="text-lg font-medium text-slate-900 dark:text-slate-100">
                {{ $selectedTerm->label }}
                @if ($selectedTerm->is_current)
                    <span class="splis-badge-linked ml-2">Current</span>
                @endif
            </p>
        </div>

        @if ($hasAssignments)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @include('board-members.partials.role-list', [
                    'title' => 'Chairmanship',
                    'memberships' => $roles['chair'],
                ])
                @include('board-members.partials.role-list', [
                    'title' => 'Vice Chairmanship',
                    'memberships' => $roles['vice_chair'],
                ])
                @include('board-members.partials.role-list', [
                    'title' => 'Committee Membership',
                    'memberships' => $roles['member'],
                ])
            </div>
        @else
            <p class="text-sm text-slate-500">No Committee Assignments for {{ $selectedTerm->label }} yet. Assign this member from a committee roster.</p>
        @endif
        </div>
    </div>

    @if ($otherTerms->isNotEmpty())
        <div class="space-y-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Other terms</h2>

            @foreach ($otherTerms as $entry)
                <div class="splis-card overflow-hidden">
                    <div class="splis-card-header splis-card-header--emphasis flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="splis-card-title">{{ $entry['term']->label }}</h2>
                            @if ($entry['term']->year_from || $entry['term']->year_to)
                                <p class="splis-card-subtitle">{{ $entry['term']->year_from ?? '?' }}–{{ $entry['term']->year_to ?? 'present' }}</p>
                            @endif
                        </div>
                        <a href="{{ route('board-members.show', ['boardMember' => $boardMember, 'term' => $entry['term']->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-sm">
                            <x-icon name="eye" class="h-4 w-4" />
                            View Term
                        </a>
                    </div>
                    <div class="splis-card-body">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        @include('board-members.partials.role-list', [
                            'title' => 'Chairmanship',
                            'memberships' => $entry['roles']['chair'],
                            'empty' => '—',
                        ])
                        @include('board-members.partials.role-list', [
                            'title' => 'Vice Chairmanship',
                            'memberships' => $entry['roles']['vice_chair'],
                            'empty' => '—',
                        ])
                        @include('board-members.partials.role-list', [
                            'title' => 'Committee membership',
                            'memberships' => $entry['roles']['member'],
                            'empty' => '—',
                        ])
                    </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @include('partials.detail-prev-next', [
        'previous' => $previousBoardMember ?? null,
        'next' => $nextBoardMember ?? null,
        'previousUrl' => ($previousBoardMember ?? null) ? route('board-members.show', ['boardMember' => $previousBoardMember, 'term' => $selectedTerm->id]) : null,
        'nextUrl' => ($nextBoardMember ?? null) ? route('board-members.show', ['boardMember' => $nextBoardMember, 'term' => $selectedTerm->id]) : null,
        'previousLabel' => isset($previousBoardMember) ? $previousBoardMember->displayName() : null,
        'nextLabel' => isset($nextBoardMember) ? $nextBoardMember->displayName() : null,
        'label' => 'Board member navigation',
    ])

    <div class="mt-6">
        <a href="{{ route('board-members.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back to Board Members
        </a>
    </div>
</div>
@endsection
