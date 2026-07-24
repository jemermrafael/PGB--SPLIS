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
        <div class="flex min-w-0 items-start gap-3">
            @php
                $showCustomUrl = \App\Support\CommitteeIcon::customUrl($committee);
                $showIconKey = \App\Support\CommitteeIcon::resolveKey($committee);
                $showIconPath = \App\Support\CommitteeIcon::pathFor($showIconKey);
            @endphp
            <span class="mt-0.5 inline-flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-800 dark:bg-brand-900/40 dark:text-brand-100" aria-hidden="true">
                @if ($showCustomUrl)
                    <span class="splis-list-committee-icon-glyph splis-list-committee-icon-glyph--lg" style="--committee-icon: url('{{ $showCustomUrl }}')"></span>
                @else
                    <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $showIconPath }}" />
                    </svg>
                @endif
            </span>
            <div class="min-w-0">
                <h1 class="splis-page-title">{{ $committee->name }}</h1>
                <p class="splis-page-subtitle">Committee roster by election term.</p>
            </div>
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
            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <x-icon name="calendar" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                Term
            </p>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-lg font-medium text-slate-900 dark:text-slate-100">
                {{ $selectedTerm->label }}
                @if ($selectedTerm->is_current)
                    <span class="splis-badge-linked">Current</span>
                @endif
            </p>
        </div>

        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <dt class="splis-label inline-flex items-center gap-1.5">
                    <x-icon name="user" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    Chair
                </dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    @include('committees.partials.roster-member-link', [
                        'boardMember' => $chair,
                        'fallback' => $allowLegacy ? $committee->chair : null,
                        'term' => $selectedTerm,
                    ])
                </dd>
            </div>
            <div>
                <dt class="splis-label inline-flex items-center gap-1.5">
                    <x-icon name="user" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    Vice chair
                </dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    @include('committees.partials.roster-member-link', [
                        'boardMember' => $viceChair,
                        'fallback' => $allowLegacy ? $committee->vice_chair : null,
                        'term' => $selectedTerm,
                    ])
                </dd>
            </div>
            <div>
                <dt class="splis-label inline-flex items-center gap-1.5">
                    <x-icon name="edit" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    Secretary
                </dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">
                    {{ $committee->secretaryDisplayName($selectedTerm->id, $allowLegacy) ?: '—' }}
                </dd>
            </div>
            <div>
                <dt class="splis-label inline-flex items-center gap-1.5">
                    <x-icon name="mail" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    Email
                </dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $committee->email ?: '—' }}</dd>
            </div>
        </dl>

        <div>
            <dt class="splis-label mb-2 inline-flex items-center gap-1.5">
                <x-icon name="users" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                Members
            </dt>
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
