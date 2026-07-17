@extends('layouts.app')

@section('title', $session->displayTitle().' — Order of Business — '.config('app.name'))

@section('content')
@php
    $sessionDateOver = $session->isPastSessionDate();
@endphp
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
        <div class="flex flex-wrap justify-end gap-2">
            @if (auth()->user()?->canRecordAttendance())
                <a href="{{ route('ob.sessions.attendance', $session) }}" @class([
                    'inline-flex items-center gap-2',
                    $sessionDateOver ? 'splis-btn-primary' : 'splis-btn-secondary',
                ])>
                    <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
                    Attendance
                </a>
            @endif
            @if ($session->obDocument)
                @can('update', $session->obDocument)
                    @unless ($sessionDateOver)
                        <a href="{{ route('ob.document.maker', $session) }}" class="splis-btn-primary inline-flex items-center gap-2">
                            <x-icon name="edit" class="h-4 w-4 shrink-0" />
                            Open OB Maker
                        </a>
                    @endunless
                    <a href="{{ route('ob.document.print', $session) }}" target="_blank" class="splis-btn-secondary inline-flex items-center gap-2">
                        <x-icon name="printer" class="h-4 w-4 shrink-0" />
                        Print Preview
                    </a>
                @elsecan('view', $session->obDocument)
                    <a href="{{ route('ob.document.print', $session) }}" class="splis-btn-primary inline-flex items-center gap-2">
                        <x-icon name="eye" class="h-4 w-4 shrink-0" />
                        View Order of Business
                    </a>
                @endcan
            @endif
            @can('update', $session)
                <a href="{{ route('ob.sessions.edit', $session) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                    <x-icon name="edit" class="h-4 w-4 shrink-0" />
                    Edit Session
                </a>
            @endcan
            <a href="{{ route('ob.sessions.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4 shrink-0" />
                Back to list
            </a>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="splis-card lg:col-span-2">
            <div class="splis-card-header">
                <h2 class="splis-card-title">Session Details</h2>
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
                        @unless ($sessionDateOver)
                            <a href="{{ route('ob.document.maker', $session) }}" class="splis-link text-sm">Edit document in OB Maker →</a>
                        @endunless
                    @elsecan('view', $session->obDocument)
                        <a href="{{ route('ob.document.print', $session) }}" class="splis-link text-sm">View Order of Business →</a>
                    @endcan
                @else
                    <p class="text-sm text-slate-600 dark:text-slate-400">No OB document linked.</p>
                @endif
            </div>
        </div>
    </div>

    @php
        $sessionPdfRows = $session->sessionPdfLinkRows();
        $sessionPdfEmbeddable = collect($sessionPdfRows)->filter(fn (array $link) => filled($link['url']));
    @endphp

    <div class="splis-card mb-6">
        <div class="splis-card-header flex items-center justify-between gap-3">
            <div>
                <h2 class="splis-card-title">Session Documents</h2>
                <p class="splis-card-subtitle">Committee Reports, Journal, and Minutes</p>
            </div>
            @can('update', $session)
                <a href="{{ route('ob.sessions.edit', $session) }}" class="splis-link text-sm whitespace-nowrap">Edit links</a>
            @endcan
        </div>
        <div class="splis-card-body">
            <ul class="grid grid-cols-1 gap-x-10 gap-y-4 sm:grid-cols-2">
                @foreach ($sessionPdfRows as $link)
                    <li class="flex items-center justify-between gap-4 rounded-lg border border-slate-100 px-3 py-2.5 dark:border-slate-800">
                        <span class="min-w-0 truncate text-sm font-medium text-slate-700 dark:text-slate-300">{{ $link['label'] }}</span>
                        @if ($link['url'])
                            <button
                                type="button"
                                class="splis-btn-secondary inline-flex shrink-0 items-center gap-2 text-sm"
                                data-session-pdf-open
                                data-pdf-src="{{ \App\Support\PdfEmbedUrl::forIframe($link['url']) }}"
                                data-pdf-title="{{ $link['label'] }}"
                                data-pdf-url="{{ $link['url'] }}"
                            >
                                <x-icon name="eye" class="h-4 w-4" />
                                View file
                            </button>
                        @else
                            <span class="shrink-0 text-sm text-slate-400">No link</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @if ($sessionPdfEmbeddable->isNotEmpty())
        <div id="session-pdf-modal" class="splis-modal" hidden>
            <div class="splis-modal-backdrop" data-session-pdf-close tabindex="-1" aria-hidden="true"></div>
            <div class="splis-modal-panel !max-h-[92vh] !max-w-5xl" role="dialog" aria-modal="true" aria-labelledby="session-pdf-modal-title">
                <div class="splis-modal-header">
                    <h3 id="session-pdf-modal-title" class="splis-modal-title">Session document</h3>
                    <div class="flex items-center gap-2">
                        <a
                            id="session-pdf-open-tab"
                            href="#"
                            target="_blank"
                            rel="noopener"
                            class="splis-btn-ghost inline-flex items-center gap-1.5 !px-2 !py-1 text-xs"
                        >
                            <x-icon name="external-link" class="h-3.5 w-3.5" />
                            Open in new tab
                        </a>
                        <button type="button" class="splis-modal-close" data-session-pdf-close aria-label="Close">×</button>
                    </div>
                </div>
                <div class="splis-modal-body !p-0">
                    <iframe
                        id="session-pdf-frame"
                        title="Session document PDF preview"
                        class="block h-[75vh] w-full border-0 bg-slate-100 dark:bg-slate-900"
                        src="about:blank"
                    ></iframe>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
            (function () {
                const modal = document.getElementById('session-pdf-modal');
                const frame = document.getElementById('session-pdf-frame');
                const title = document.getElementById('session-pdf-modal-title');
                const openTab = document.getElementById('session-pdf-open-tab');
                const triggers = document.querySelectorAll('[data-session-pdf-open]');

                if (! modal || ! frame || ! title || ! openTab || triggers.length === 0) {
                    return;
                }

                function openModal(trigger) {
                    const src = trigger.dataset.pdfSrc || '';
                    const label = trigger.dataset.pdfTitle || 'Session document';
                    const url = trigger.dataset.pdfUrl || src;

                    title.textContent = label;
                    openTab.href = url;
                    frame.setAttribute('src', src);
                    modal.hidden = false;
                    document.body.classList.add('splis-modal-open');
                }

                function closeModal() {
                    modal.hidden = true;
                    document.body.classList.remove('splis-modal-open');
                    frame.setAttribute('src', 'about:blank');
                }

                triggers.forEach((trigger) => {
                    trigger.addEventListener('click', () => openModal(trigger));
                });

                modal.querySelectorAll('[data-session-pdf-close]').forEach((el) => {
                    el.addEventListener('click', closeModal);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && ! modal.hidden) {
                        closeModal();
                    }
                });
            })();
        </script>
        @endpush
    @endif

    @if ($session->obDocument && $session->obDocument->blocks->isNotEmpty())
        <div class="splis-card overflow-hidden">
            <div class="splis-card-header flex items-center justify-between">
                <div>
                    <h2 class="splis-card-title">Document Outline</h2>
                    <p class="splis-card-subtitle">{{ $session->obDocument->blocks->count() }} blocks</p>
                </div>
                @can('update', $session->obDocument)
                    @unless ($sessionDateOver)
                        <a href="{{ route('ob.document.maker', $session) }}" class="splis-link text-sm">Open OB Maker</a>
                    @endunless
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
            <form method="POST" action="{{ route('ob.sessions.destroy', $session) }}" onsubmit="return confirm('Move this Order of Business session to trash?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="splis-btn-danger">Delete</button>
            </form>
        </div>
    @endcan
</div>
@endsection
