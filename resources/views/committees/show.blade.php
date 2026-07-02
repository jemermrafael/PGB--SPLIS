@extends('layouts.app')

@php
    $allowLegacy = $selectedTerm->is_current;
    $chairName = optional($memberships->get('chair')?->first()?->boardMember)->displayName();
    $viceChairName = optional($memberships->get('vice_chair')?->first()?->boardMember)->displayName();

    if ($allowLegacy) {
        $chairName = $chairName ?: ($committee->chair ?: null);
        $viceChairName = $viceChairName ?: ($committee->vice_chair ?: null);
    }

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
            <a href="{{ route('committees.edit', ['committee' => $committee, 'term' => $selectedTerm->id]) }}" class="splis-btn-primary">Edit roster</a>
        @endcan
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($terms as $term)
            <a
                href="{{ route('committees.show', ['committee' => $committee, 'term' => $term->id]) }}"
                class="{{ $term->id === $selectedTerm->id ? 'splis-btn-primary' : 'splis-btn-secondary' }} text-sm"
            >
                {{ $term->label }}
            </a>
        @endforeach
    </div>

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
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $chairName ?: '—' }}</dd>
            </div>
            <div>
                <dt class="splis-label">Vice chair</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $viceChairName ?: '—' }}</dd>
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
                            <li>{{ $membership->boardMember?->displayName() }}</li>
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

    <div class="mt-4">
        <a href="{{ route('committees.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-secondary">Back to committees</a>
    </div>
</div>
@endsection
