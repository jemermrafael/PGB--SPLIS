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
            @can('update', $session->obDocument)
                <a href="{{ route('ob.document.maker', $session) }}" class="splis-btn-primary inline-flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                    </svg>
                    Open OB Maker
                </a>
                <a href="{{ route('ob.document.print', $session) }}" target="_blank" class="splis-btn-secondary inline-flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18M6.34 18H4.5a2.25 2.25 0 01-2.25-2.25v-3.006A2.25 2.25 0 014.5 9.75h15a2.25 2.25 0 012.25 2.25v3.006A2.25 2.25 0 0119.5 18h-1.84M9.75 9.75h4.5V6.75a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75v3zM9.75 18v1.125c0 .621.504 1.125 1.125 1.125h2.25c.621 0 1.125-.504 1.125-1.125V18"/>
                    </svg>
                    Print preview
                </a>
            @elsecan('view', $session->obDocument)
                <a href="{{ route('ob.document.print', $session) }}" class="splis-btn-primary inline-flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    View Order of Business
                </a>
            @endcan
            @if (auth()->user()?->canRecordAttendance())
                <a href="{{ route('ob.sessions.attendance', $session) }}" class="splis-btn-secondary">Attendance</a>
            @endif
            @can('update', $session)
                <a href="{{ route('ob.sessions.edit', $session) }}" class="splis-btn-secondary">Edit Session</a>
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
                    @can('update', $session->obDocument)
                        <a href="{{ route('ob.document.maker', $session) }}" class="splis-link text-sm">Edit document in OB Maker →</a>
                    @elsecan('view', $session->obDocument)
                        <a href="{{ route('ob.document.print', $session) }}" class="splis-link text-sm">View Order of Business →</a>
                    @endcan
                @else
                    <p class="text-sm text-slate-600 dark:text-slate-400">No OB document linked.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="splis-card mb-6">
        <div class="splis-card-header flex items-center justify-between">
            <div>
                <h2 class="splis-card-title">Session documents</h2>
                <p class="splis-card-subtitle">PDF links for committee reports, journal, and minutes</p>
            </div>
            @can('update', $session)
                <a href="{{ route('ob.sessions.edit', $session) }}" class="splis-link text-sm">Edit links</a>
            @endcan
        </div>
        <dl class="splis-card-body divide-y divide-slate-200 dark:divide-slate-700">
            @foreach ($session->sessionPdfLinkRows() as $link)
                <div class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                    <dt class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $link['label'] }}</dt>
                    <dd class="text-sm">
                        @if ($link['url'])
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="splis-link">Open PDF</a>
                        @else
                            <span class="text-slate-400">No link yet</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    @if ($session->obDocument && $session->obDocument->blocks->isNotEmpty())
        <div class="splis-card overflow-hidden">
            <div class="splis-card-header flex items-center justify-between">
                <div>
                    <h2 class="splis-card-title">Document outline</h2>
                    <p class="splis-card-subtitle">{{ $session->obDocument->blocks->count() }} blocks</p>
                </div>
                @can('update', $session->obDocument)
                    <a href="{{ route('ob.document.maker', $session) }}" class="splis-link text-sm">Open maker</a>
                @elsecan('view', $session->obDocument)
                    <a href="{{ route('ob.document.print', $session) }}" class="splis-link text-sm">View OB</a>
                @endcan
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
