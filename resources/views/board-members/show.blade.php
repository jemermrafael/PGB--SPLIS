@extends('layouts.app')

@section('title', $boardMember->displayName().' — Board Members — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header">
        <div>
            <p class="text-sm text-slate-500">{{ $boardMember->district ?: 'Board member' }}</p>
            <h1 class="splis-page-title">{{ $boardMember->displayName() }}</h1>
            <p class="splis-page-subtitle">Committee assignments for the current term and past election periods.</p>
        </div>
        @can('update', $boardMember)
            <a href="{{ route('board-members.edit', $boardMember) }}" class="splis-btn-primary">Edit profile</a>
        @endcan
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($boardMember->is_active)
            <span class="splis-badge-linked">Active</span>
        @else
            <span class="splis-badge-unlinked">Inactive</span>
        @endif
        @if ($boardMember->district === 'Vice Governor')
            <span class="splis-badge-linked">Presiding officer of the Sangguniang Panlalawigan</span>
        @endif
    </div>

    <div class="splis-card splis-card-body mb-8 space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current term</p>
            <p class="text-lg font-medium text-slate-900 dark:text-slate-100">
                {{ $currentTerm->label }}
                <span class="splis-badge-linked ml-2">Current</span>
            </p>
        </div>

        @php
            $hasCurrentAssignments = collect($currentRoles)->flatten()->isNotEmpty();
        @endphp

        @if ($hasCurrentAssignments)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @include('board-members.partials.role-list', [
                    'title' => 'Chairmanship',
                    'memberships' => $currentRoles['chair'],
                ])
                @include('board-members.partials.role-list', [
                    'title' => 'Vice chairmanship',
                    'memberships' => $currentRoles['vice_chair'],
                ])
                @include('board-members.partials.role-list', [
                    'title' => 'Committee membership',
                    'memberships' => $currentRoles['member'],
                ])
            </div>
        @else
            <p class="text-sm text-slate-500">No committee assignments for the current term yet. Assign this member from a committee roster.</p>
        @endif
    </div>

    <div class="space-y-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Historical assignments</h2>

        @forelse ($history as $entry)
            <div class="splis-card splis-card-body space-y-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Past term</p>
                    <p class="text-base font-medium text-slate-900 dark:text-slate-100">{{ $entry['term']->label }}</p>
                    @if ($entry['term']->year_from || $entry['term']->year_to)
                        <p class="text-sm text-slate-500">{{ $entry['term']->year_from ?? '?' }}–{{ $entry['term']->year_to ?? 'present' }}</p>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    @include('board-members.partials.role-list', [
                        'title' => 'Chairmanship',
                        'memberships' => $entry['roles']['chair'],
                        'empty' => '—',
                    ])
                    @include('board-members.partials.role-list', [
                        'title' => 'Vice chairmanship',
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
        @empty
            <div class="splis-card splis-card-body text-sm text-slate-500">
                No past-term records yet. When you add earlier election periods, this member’s previous chairmanships and memberships will appear here.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        <a href="{{ route('board-members.index') }}" class="splis-btn-secondary">Back to board members</a>
    </div>
</div>
@endsection
