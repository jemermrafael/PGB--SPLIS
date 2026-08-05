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
                    Edit Profile
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
        <details class="splis-card mt-6 overflow-hidden splis-accordion">
            <summary class="splis-accordion-summary !px-5 !py-4">
                <div class="splis-accordion-summary-top">
                    <div class="min-w-0">
                        <h2 class="splis-card-title">Other terms</h2>
                        <p class="splis-card-subtitle">Committee Assignments in previous election periods</p>
                    </div>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="splis-accordion-count">{{ number_format($otherTerms->count()) }}</span>
                        <svg class="splis-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </span>
                </div>
            </summary>
            <div class="splis-accordion-body !space-y-6 !px-5 !py-5">
                @foreach ($otherTerms as $entry)
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/60">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $entry['term']->label }}</h3>
                                @if ($entry['term']->year_from || $entry['term']->year_to)
                                    <p class="text-sm text-slate-500">{{ $entry['term']->year_from ?? '?' }}–{{ $entry['term']->year_to ?? 'present' }}</p>
                                @endif
                            </div>
                            <a href="{{ route('board-members.show', ['boardMember' => $boardMember, 'term' => $entry['term']->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-sm">
                                <x-icon name="eye" class="h-4 w-4" />
                                View Term
                            </a>
                        </div>
                        <div class="bg-white p-4 dark:bg-slate-900/40">
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
        </details>
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
