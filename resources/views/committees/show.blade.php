@extends('layouts.app')

@php
    $allowLegacy = $selectedTerm->is_current;
    $chair = $memberships->get('chair')?->first()?->boardMember;
    $viceChair = $memberships->get('vice_chair')?->first()?->boardMember;
    $memberRows = $memberships->get('member') ?? collect();
@endphp

@section('title', $committee->name.' — Committees — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">{{ $committee->name }}</h1>
            <p class="splis-page-subtitle">Committee roster by election term.</p>
        </div>
        @can('update', $committee)
            <a href="{{ route('committees.edit', ['committee' => $committee, 'term' => $selectedTerm->id]) }}" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="edit" class="h-4 w-4" />
                Edit Roster
            </a>
        @endcan
    </div>

    @include('partials.term-switcher', [
        'terms' => $terms,
        'selectedTerm' => $selectedTerm,
        'routeName' => 'committees.show',
        'routeParams' => ['committee' => $committee],
        'showCurrentBadge' => false,
    ])

    <div class="splis-card splis-card-body space-y-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Term</p>
            <p class="text-lg font-medium text-slate-900 dark:text-slate-100">
                {{ $selectedTerm->label }}
                @if ($selectedTerm->is_current)
                    <span class="splis-badge-linked ml-2">Current</span>
                @endif
            </p>
        </div>

        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <dt class="splis-label">Chair</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    @include('committees.partials.roster-member-link', [
                        'boardMember' => $chair,
                        'fallback' => $allowLegacy ? $committee->chair : null,
                        'term' => $selectedTerm,
                    ])
                </dd>
            </div>
            <div>
                <dt class="splis-label">Vice chair</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    @include('committees.partials.roster-member-link', [
                        'boardMember' => $viceChair,
                        'fallback' => $allowLegacy ? $committee->vice_chair : null,
                        'term' => $selectedTerm,
                    ])
                </dd>
            </div>
            <div>
                <dt class="splis-label">Secretary</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    {{ $committee->secretaryDisplayName($selectedTerm->id, $allowLegacy) ?: '—' }}
                </dd>
            </div>
            <div>
                <dt class="splis-label">Email</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $committee->email ?: '—' }}</dd>
            </div>
        </dl>

        <div>
            <dt class="splis-label mb-2">Members</dt>
            <dd>
                @if ($memberRows->isNotEmpty())
                    <ul class="list-inside list-disc space-y-1 text-slate-900 dark:text-slate-100">
                        @foreach ($memberRows as $membership)
                            <li>
                                @include('committees.partials.roster-member-link', [
                                    'boardMember' => $membership->boardMember,
                                    'fallback' => null,
                                    'term' => $selectedTerm,
                                ])
                            </li>
                        @endforeach
                    </ul>
                @elseif ($allowLegacy && $committee->members)
                    <pre class="whitespace-pre-wrap font-sans text-slate-900 dark:text-slate-100">{{ $committee->members }}</pre>
                @else
                    <span class="text-slate-500">—</span>
                @endif
            </dd>
        </div>
    </div>

    @include('partials.detail-prev-next', [
        'previous' => $previousCommittee ?? null,
        'next' => $nextCommittee ?? null,
        'previousUrl' => ($previousCommittee ?? null) ? route('committees.show', ['committee' => $previousCommittee, 'term' => $selectedTerm->id]) : null,
        'nextUrl' => ($nextCommittee ?? null) ? route('committees.show', ['committee' => $nextCommittee, 'term' => $selectedTerm->id]) : null,
        'previousLabel' => isset($previousCommittee) ? $previousCommittee->name : null,
        'nextLabel' => isset($nextCommittee) ? $nextCommittee->name : null,
        'label' => 'Committee navigation',
    ])

    <div class="mt-4">
        <a href="{{ route('committees.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back to Committees
        </a>
    </div>
</div>
@endsection
