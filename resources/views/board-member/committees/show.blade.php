@extends('layouts.app')

@php
    $chair = $roster->get('chair')?->first()?->boardMember;
    $viceChair = $roster->get('vice_chair')?->first()?->boardMember;
    $members = $roster->get('member') ?? collect();
    $allowLegacy = $selectedTerm->is_current;
    $showCustomIcon = \App\Support\CommitteeIcon::customIcon($committee);
    $showIconKey = \App\Support\CommitteeIcon::resolveKey($committee);
    $showIconPath = \App\Support\CommitteeIcon::pathFor($showIconKey);
@endphp

@section('title', $committee->name.' — My Committees — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div class="min-w-0">
            <p class="mb-1 text-sm text-slate-500">
                <a href="{{ route('board-member.committees.index', ['term' => $selectedTerm->id]) }}" class="splis-link">My Committees</a>
                <span class="mx-1">/</span>
                <span>{{ $committee->name }}</span>
            </p>
            <div class="flex min-w-0 items-start gap-3">
                <span class="splis-committee-icon-frame" aria-hidden="true">
                    @if ($showCustomIcon)
                        @if ($showCustomIcon['preserve_colors'])
                            <img src="{{ $showCustomIcon['url'] }}" alt="" class="splis-list-committee-icon-img splis-list-committee-icon-img--lg">
                        @else
                            <span class="splis-list-committee-icon-glyph splis-list-committee-icon-glyph--lg" style="--committee-icon: url('{{ $showCustomIcon['url'] }}')"></span>
                        @endif
                    @else
                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $showIconPath }}" />
                        </svg>
                    @endif
                </span>
                <div class="min-w-0">
                    <h1 class="splis-page-title">{{ $committee->name }}</h1>
                    <p class="splis-page-subtitle">Your role: {{ $roleLabel }} · {{ $selectedTerm->label }}</p>
                </div>
            </div>
        </div>
        <a href="{{ route('board-member.agenda.committee', $committee) }}" class="splis-btn-secondary">Search Agenda</a>
    </div>

    @include('partials.term-switcher', [
        'terms' => $terms,
        'selectedTerm' => $selectedTerm,
        'routeName' => 'board-member.committees.show',
        'routeParams' => ['committee' => $committee],
    ])

    <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="splis-card splis-card-body space-y-5">
            <div>
                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <x-icon name="users" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    Committee roster
                </p>
                <p class="text-sm text-slate-500">{{ $selectedTerm->label }}</p>
            </div>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <p class="splis-label mb-2 inline-flex items-center gap-1.5">
                    <x-icon name="users" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    Members
                </p>
                @if ($members->isNotEmpty())
                    <ul class="space-y-2 text-slate-900 dark:text-slate-100">
                        @foreach ($members as $membership)
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
                    <p class="text-sm text-slate-500">No members listed for this term.</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 content-start">
            <div class="splis-stat splis-stat--gold text-left">
                <div class="splis-stat-icon splis-stat-icon--gold">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="splis-stat-label">Pending</p>
                <p class="splis-stat-value">{{ number_format($stats['pending']) }}</p>
            </div>
            <div class="splis-stat splis-stat--brand text-left">
                <div class="splis-stat-icon splis-stat-icon--brand">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                </div>
                <p class="splis-stat-label">Due soon</p>
                <p class="splis-stat-value">{{ number_format($stats['due_soon']) }}</p>
            </div>
            <div class="splis-stat splis-stat--green text-left">
                <div class="splis-stat-icon splis-stat-icon--green">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="splis-stat-label">Accomplished</p>
                <p class="splis-stat-value">{{ number_format($stats['done']) }}</p>
            </div>
            <div class="splis-stat splis-stat--sky text-left">
                <div class="splis-stat-icon splis-stat-icon--sky">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                </div>
                <p class="splis-stat-label">Lapsed</p>
                <p class="splis-stat-value">{{ number_format($stats['lapsed']) }}</p>
            </div>
        </div>
    </div>

    <div class="splis-card">
        <div class="splis-card-header flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="splis-card-title">Agenda items</h2>
                <p class="splis-card-subtitle">Referrals to {{ $committee->name }}</p>
            </div>
            <a href="{{ route('board-member.agenda.committee', $committee) }}" class="splis-link text-sm">Full Agenda search</a>
        </div>
        <div class="splis-table-wrap">
            <table class="splis-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="min-w-[12rem] max-w-md">Title</th>
                        <th class="hidden md:table-cell">Sender</th>
                        <th class="hidden sm:table-cell">Referred</th>
                        <th>Status</th>
                        <th class="w-16"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agendas as $item)
                        <tr>
                            <td class="whitespace-nowrap font-semibold">
                                <a href="{{ route('agenda.show', $item) }}" class="splis-doc-list-link">{{ $item->displayLabel() }}</a>
                            </td>
                            <td class="splis-table-title">{{ \Illuminate\Support\Str::limit($item->title ?: 'Untitled', 100) }}</td>
                            <td class="hidden md:table-cell">{{ $item->sender ?: '—' }}</td>
                            <td class="hidden sm:table-cell whitespace-nowrap">{{ $item->date_of_referral?->format('M j, Y') ?: '—' }}</td>
                            <td>
                                <span class="splis-agenda-status splis-agenda-status--{{ $item->status }}">
                                    {{ $statuses[$item->status] ?? $item->status }}
                                </span>
                            </td>
                            <td><a href="{{ route('agenda.show', $item) }}" class="splis-link text-sm">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No Agenda items referred to this committee.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
