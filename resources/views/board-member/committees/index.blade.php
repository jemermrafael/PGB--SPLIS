@extends('layouts.app')

@section('title', 'My Committees — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">My Committees</h1>
            <p class="splis-page-subtitle">
                Your Chairmanship, Vice Chairmanship, and Membership for
                {{ $selectedTerm->label }}@if ($selectedTerm->is_current) (current term)@endif.
            </p>
        </div>
        <a href="{{ route('dashboard') }}" class="splis-btn-ghost">Dashboard</a>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a Board Member profile yet. Please contact the SP office administrator.</div>
    @else
        @if ($terms->count() > 1)
            <div class="mb-6 flex flex-wrap gap-2">
                @foreach ($terms as $term)
                    <a
                        href="{{ route('board-member.committees.index', ['term' => $term->id]) }}"
                        class="{{ $term->id === $selectedTerm->id ? 'splis-btn-primary' : 'splis-btn-secondary' }} text-sm"
                    >
                        {{ $term->label }}@if ($term->is_current) (current)@endif
                    </a>
                @endforeach
            </div>
        @endif

        <p class="mb-6 text-sm text-slate-500">{{ $totalAssignments }} committee assignment(s) in this term.</p>

        <div class="space-y-6">
            @include('board-member.committees.partials.role-section', [
                'title' => 'Chairmanship',
                'assignments' => $roles['chair'],
                'empty' => 'You are not chair of any committee this term.',
                'badge' => 'Chair',
                'selectedTerm' => $selectedTerm,
            ])
            @include('board-member.committees.partials.role-section', [
                'title' => 'Vice Chairmanship',
                'assignments' => $roles['vice_chair'],
                'empty' => 'You are not vice chair of any committee this term.',
                'badge' => 'Vice chair',
                'selectedTerm' => $selectedTerm,
            ])
            @include('board-member.committees.partials.role-section', [
                'title' => 'Membership',
                'assignments' => $roles['member'],
                'empty' => 'You are not a member of any committee this term.',
                'badge' => 'Member',
                'selectedTerm' => $selectedTerm,
            ])
        </div>
    @endif
</div>
@endsection
