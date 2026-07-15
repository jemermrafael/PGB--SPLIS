@extends('layouts.app')

@php
    $district = $assignment?->district;
    $isActive = $assignment?->is_active ?? false;
    $hasAssignments = collect($roles)->flatten()->isNotEmpty();
@endphp

@section('title', $boardMember->displayName().' — Board Members — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header">
        <div>
            <p class="text-sm text-slate-500">{{ $district ?: 'Board member' }}</p>
            <h1 class="splis-page-title">{{ $boardMember->displayName() }}</h1>
            <p class="splis-page-subtitle">Committee assignments for the selected election term.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $boardMember)
                <a href="{{ route('board-members.edit', ['boardMember' => $boardMember, 'term' => $selectedTerm->id]) }}" class="splis-btn-primary">Edit profile</a>
            @endcan
            @can('delete', $boardMember)
                <form
                    method="POST"
                    action="{{ route('board-members.destroy', $boardMember) }}"
                    data-confirm-submit
                    data-confirm-title="Delete Board Member?"
                    data-confirm-message="Delete {{ $boardMember->displayName() }}? This removes their roster and committee assignments."
                    data-confirm-label="Delete"
                >
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="term" value="{{ $selectedTerm->id }}">
                    <button type="submit" class="splis-btn-ghost text-red-600">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($terms as $term)
            <a
                href="{{ route('board-members.show', ['boardMember' => $boardMember, 'term' => $term->id]) }}"
                class="{{ $term->id === $selectedTerm->id ? 'splis-btn-primary' : 'splis-btn-secondary' }} text-sm"
            >
                {{ $term->label }}@if ($term->is_current) (current)@endif
            </a>
        @endforeach
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($assignment)
            @if ($isActive)
                <span class="splis-badge-linked">Active on roster</span>
            @else
                <span class="splis-badge-unlinked">Inactive on roster</span>
            @endif
            @if ($district === 'Vice Governor')
                <span class="splis-badge-linked">Presiding officer of the Sangguniang Panlalawigan</span>
            @endif
        @else
            <span class="splis-badge-unlinked">Not on {{ $selectedTerm->label }} roster</span>
        @endif
    </div>

    <div class="splis-card splis-card-body mb-8 space-y-6">
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
            <p class="text-sm text-slate-500">No committee assignments for {{ $selectedTerm->label }} yet. Assign this member from a committee roster.</p>
        @endif
    </div>

    @if ($otherTerms->isNotEmpty())
        <div class="space-y-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Other terms</h2>

            @foreach ($otherTerms as $entry)
                <div class="splis-card splis-card-body space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Past term</p>
                            <p class="text-base font-medium text-slate-900 dark:text-slate-100">{{ $entry['term']->label }}</p>
                            @if ($entry['term']->year_from || $entry['term']->year_to)
                                <p class="text-sm text-slate-500">{{ $entry['term']->year_from ?? '?' }}–{{ $entry['term']->year_to ?? 'present' }}</p>
                            @endif
                        </div>
                        <a href="{{ route('board-members.show', ['boardMember' => $boardMember, 'term' => $entry['term']->id]) }}" class="splis-btn-secondary text-sm">View term</a>
                    </div>

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
            @endforeach
        </div>
    @endif

    <div class="mt-6">
        <a href="{{ route('board-members.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-secondary">Back to Board Members</a>
    </div>
</div>
@endsection
