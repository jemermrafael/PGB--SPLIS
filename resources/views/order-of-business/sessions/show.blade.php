@extends('layouts.app')

@section('title', $session->displayTitle().' — Order of Business — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header !mb-6">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="splis-badge">{{ $session->statusLabel() }}</span>
                <span class="splis-badge">{{ $session->sessionKindLabel() }}</span>
                @if ($session->obDocument)
                    <span class="splis-badge-linked">{{ $session->obDocument->statusLabel() }} OB</span>
                @endif
            </div>
            <h1 class="splis-page-title">{{ $session->displayTitle() }}</h1>
            @if ($session->venue)
                <p class="splis-page-subtitle">{{ $session->venue }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($session->obDocument)
                <a href="{{ route('ob.document.maker', $session) }}" class="splis-btn-primary">Open OB Maker</a>
                <a href="{{ route('ob.document.print', $session) }}" target="_blank" class="splis-btn-secondary">Print preview</a>
            @endif
            @can('update', $session)
                <a href="{{ route('ob.sessions.edit', $session) }}" class="splis-btn-secondary">Edit session</a>
            @endcan
            <a href="{{ route('ob.sessions.index') }}" class="splis-btn-secondary">Back to list</a>
        </div>
    </div>

    @if (session('status'))
        <div class="splis-alert-success mb-6">{{ session('status') }}</div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="splis-card lg:col-span-2">
            <div class="splis-card-header">
                <h2 class="splis-card-title">Session details</h2>
            </div>
            <dl class="splis-card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="splis-detail-label">Date</dt>
                    <dd class="mt-1 font-medium">{{ $session->session_date->format('l, F j, Y') }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Time</dt>
                    <dd class="mt-1 font-medium">{{ $session->formattedSessionTime() ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Session</dt>
                    <dd class="mt-1 font-medium">{{ $session->session_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Prior session</dt>
                    <dd class="mt-1 font-medium">
                        @if ($session->priorSession)
                            <a href="{{ route('ob.sessions.show', $session->priorSession) }}" class="splis-link">{{ $session->priorSession->displayTitle() }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @if ($session->notes)
                    <div class="sm:col-span-2">
                        <dt class="splis-detail-label">Notes</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $session->notes }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="splis-card">
            <div class="splis-card-header">
                <h2 class="splis-card-title">Order of Business</h2>
            </div>
            <div class="splis-card-body space-y-3">
                @if ($session->obDocument)
                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $session->obDocument->title }}</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ $session->obDocument->blocks->count() }} content blocks
                        · next agenda no. {{ $session->obDocument->next_session_agenda_no }}
                    </p>
                    <a href="{{ route('ob.document.maker', $session) }}" class="splis-link text-sm">Edit document in OB Maker →</a>
                @else
                    <p class="text-sm text-slate-600 dark:text-slate-400">No OB document linked.</p>
                @endif
            </div>
        </div>
    </div>

    @if ($session->obDocument && $session->obDocument->blocks->isNotEmpty())
        <div class="splis-card overflow-hidden">
            <div class="splis-card-header flex items-center justify-between">
                <div>
                    <h2 class="splis-card-title">Document outline</h2>
                    <p class="splis-card-subtitle">{{ $session->obDocument->blocks->count() }} blocks</p>
                </div>
                <a href="{{ route('ob.document.maker', $session) }}" class="splis-link text-sm">Open maker</a>
            </div>
            <ol class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach ($session->obDocument->blocks as $block)
                    <li class="flex items-start gap-4 px-4 py-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ $block->sort_order }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $block->type->label() }}</p>
                            <p class="mt-1 text-slate-900 dark:text-slate-100">{{ $block->previewText() ?: '—' }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @can('delete', $session)
        <div class="mt-6 flex justify-end">
            <form method="POST" action="{{ route('ob.sessions.destroy', $session) }}" onsubmit="return confirm('Delete this Order of Business session and its document? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="splis-btn-danger">Delete session</button>
            </form>
        </div>
    @endcan
</div>
@endsection
