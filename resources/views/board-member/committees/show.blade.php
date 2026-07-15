@extends('layouts.app')

@php
    $chair = $roster->get('chair')?->first()?->boardMember;
    $viceChair = $roster->get('vice_chair')?->first()?->boardMember;
    $members = $roster->get('member') ?? collect();
    $allowLegacy = $selectedTerm->is_current;
@endphp

@section('title', $committee->name.' — My Committees — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            <p class="mb-1 text-sm text-slate-500">
                <a href="{{ route('board-member.committees.index', ['term' => $selectedTerm->id]) }}" class="splis-link">My Committees</a>
                <span class="mx-1">/</span>
                <span>{{ $committee->name }}</span>
            </p>
            <h1 class="splis-page-title">{{ $committee->name }}</h1>
            <p class="splis-page-subtitle">Your role: {{ $roleLabel }} · {{ $selectedTerm->label }}</p>
        </div>
        <a href="{{ route('board-member.agenda.committee', $committee) }}" class="splis-btn-secondary">Search agenda</a>
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($terms as $term)
            <a
                href="{{ route('board-member.committees.show', ['committee' => $committee, 'term' => $term->id]) }}"
                class="{{ $term->id === $selectedTerm->id ? 'splis-btn-primary' : 'splis-btn-secondary' }} text-sm"
            >
                {{ $term->label }}@if ($term->is_current) (current)@endif
            </a>
        @endforeach
    </div>

    <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="splis-card splis-card-body space-y-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Committee roster</p>
                <p class="text-sm text-slate-500">{{ $selectedTerm->label }}</p>
            </div>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="splis-label">Chair</dt>
                    <dd class="mt-1 text-slate-900 dark:text-slate-100">
                        {{ $chair?->displayName() ?: ($allowLegacy ? ($committee->chair ?: '—') : '—') }}
                    </dd>
                </div>
                <div>
                    <dt class="splis-label">Vice chair</dt>
                    <dd class="mt-1 text-slate-900 dark:text-slate-100">
                        {{ $viceChair?->displayName() ?: ($allowLegacy ? ($committee->vice_chair ?: '—') : '—') }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="splis-label">Secretary</dt>
                    <dd class="mt-1 text-slate-900 dark:text-slate-100">
                        {{ $committee->secretaryDisplayName($selectedTerm->id, $allowLegacy) ?: '—' }}
                    </dd>
                </div>
            </dl>
            <div>
                <p class="splis-label mb-2">Members</p>
                @if ($members->isNotEmpty())
                    <ul class="list-inside list-disc space-y-1 text-slate-900 dark:text-slate-100">
                        @foreach ($members as $membership)
                            <li >
                                {{ $membership->boardMember?->displayName() }}
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
                <p class="splis-stat-label">Pending</p>
                <p class="splis-stat-value">{{ number_format($stats['pending']) }}</p>
            </div>
            <div class="splis-stat splis-stat--brand text-left">
                <p class="splis-stat-label">Due soon</p>
                <p class="splis-stat-value">{{ number_format($stats['due_soon']) }}</p>
            </div>
            <div class="splis-stat splis-stat--green text-left">
                <p class="splis-stat-label">Accomplished</p>
                <p class="splis-stat-value">{{ number_format($stats['done']) }}</p>
            </div>
            <div class="splis-stat splis-stat--sky text-left">
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
            <a href="{{ route('board-member.agenda.committee', $committee) }}" class="splis-link text-sm">Full agenda search</a>
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
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No agenda items referred to this committee.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
