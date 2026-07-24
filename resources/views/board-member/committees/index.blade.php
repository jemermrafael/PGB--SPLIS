@extends('layouts.app')

@section('title', 'My Committees — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <x-page-heading title="My Committees" icon="users">
            Your Chairmanship, Vice Chairmanship, and Membership for
            {{ $selectedTerm->label }}@if ($selectedTerm->is_current) (current term)@endif.
        </x-page-heading>
        <a href="{{ route('dashboard') }}" class="splis-btn-ghost">Dashboard</a>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a Board Member profile yet. Please contact the SP office administrator.</div>
    @else
        @if ($terms->count() > 1)
            @include('partials.term-switcher', [
                'terms' => $terms,
                'selectedTerm' => $selectedTerm,
                'routeName' => 'board-member.committees.index',
            ])
        @endif

        <p class="mb-6 text-sm text-slate-500">{{ $totalAssignments }} Committee Assignment(s) in this term.</p>

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
