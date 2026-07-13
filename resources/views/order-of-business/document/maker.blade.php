@extends('layouts.app')

@section('title', 'OB Maker — '.$session->displayTitle())

@section('content')
<div
    id="ob-maker"
    class="splis-ob-maker"
    data-can-edit="{{ $canEdit ? '1' : '0' }}"
    data-config='@json($makerConfig)'
>
    <div class="splis-ob-maker-page-header splis-page-header !mb-4">
        <div class="min-w-0">
            <p class="text-sm text-slate-500">{{ $session->displayTitle() }}</p>
            <h1 class="splis-page-title truncate">Order of Business Maker</h1>
        </div>
        <div class="splis-ob-maker-actions flex flex-wrap gap-2">
            <a href="{{ route('ob.document.print', $session) }}" target="_blank" class="splis-btn-secondary inline-flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18M6.34 18H4.5a2.25 2.25 0 01-2.25-2.25v-3.006A2.25 2.25 0 014.5 9.75h15a2.25 2.25 0 012.25 2.25v3.006A2.25 2.25 0 0119.5 18h-1.84M9.75 9.75h4.5V6.75a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75v3zM9.75 18v1.125c0 .621.504 1.125 1.125 1.125h2.25c.621 0 1.125-.504 1.125-1.125V18"/>
                </svg>
                Print Preview
            </a>
            <a href="{{ route('ob.sessions.show', $session) }}" class="splis-btn-secondary">Session</a>
            @can('delete', $session)
                <form method="POST" action="{{ route('ob.sessions.destroy', $session) }}" onsubmit="return confirm('Delete this Order of Business session and its document? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="splis-btn-danger">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="splis-ob-maker-toolbar splis-card splis-card-body mb-4 flex flex-wrap items-end gap-4">
        <div class="min-w-[200px] flex-1">
            <label class="splis-label" for="ob-doc-title">Document title</label>
            <input type="text" id="ob-doc-title" class="splis-input" value="{{ $document->title }}" @disabled(! $canEdit)>
        </div>
        <div>
            <label class="splis-label" for="ob-doc-status">Status</label>
            <select id="ob-doc-status" class="splis-select" @disabled(! $canEdit)>
                @foreach (config('order_of_business.document_statuses', []) as $value => $label)
                    <option value="{{ $value }}" @selected($document->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <p id="ob-save-status" class="text-sm text-slate-500" aria-live="polite"></p>
    </div>

    <div class="splis-ob-maker-layout">
        @if ($canEdit)
            <aside class="splis-ob-maker-sidebar splis-card">
                <div class="splis-card-header">
                    <h2 class="splis-card-title">Add content</h2>
                </div>
                <div class="splis-card-body space-y-4">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Block type</p>
                        <div id="ob-add-block-types" class="flex flex-wrap gap-2"></div>
                    </div>
                    <hr class="border-slate-200 dark:border-slate-700">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">From Agenda</p>
                        <input type="search" id="ob-agenda-search" class="splis-input mb-2" placeholder="Search title, tracking no., sender…">
                        <label class="splis-label" for="ob-agenda-section">Add to section</label>
                        <select id="ob-agenda-section" class="splis-select mb-2">
                            @foreach (config('order_of_business.agenda_sections', []) as $value => $label)
                                <option value="{{ $value }}" @selected($value === 'unassigned_regular')>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mb-2 text-xs text-slate-500">Items are inserted into the matching section of the document automatically.</p>
                        <div id="ob-agenda-pool" class="splis-ob-agenda-pool"></div>
                        <button type="button" id="ob-add-selected-agenda" class="splis-btn-primary mt-3 w-full">Add selected to document</button>
                    </div>
                </div>
            </aside>
        @endif

        <div class="splis-ob-maker-canvas splis-card min-w-0 flex-1">
            <div class="splis-card-header flex items-center justify-between gap-3">
                <div>
                    <h2 class="splis-card-title">Document</h2>
                    <p class="splis-card-subtitle">Click a block to select · edits save automatically</p>
                </div>
                @if ($canEdit)
                    <button
                        type="button"
                        id="ob-sync-agendas"
                        class="splis-btn-secondary shrink-0"
                        title="Place eligible agendas using lifecycle rules"
                    >
                        Auto-place agendas
                    </button>
                @endif
            </div>
            <div id="ob-blocks-list" class="splis-card-body space-y-3"></div>
            <div id="ob-blocks-empty" class="hidden splis-card-body text-center text-slate-500">
                No blocks yet. Add content from the sidebar.
            </div>
        </div>
    </div>

    <div id="ob-confirm-dialog" class="splis-ob-dialog" aria-hidden="true">
        <div class="splis-ob-dialog-backdrop" data-ob-confirm-cancel tabindex="-1"></div>
        <div class="splis-ob-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="ob-confirm-title">
            <h3 id="ob-confirm-title" class="splis-ob-dialog-title">Delete block?</h3>
            <p id="ob-confirm-message" class="splis-ob-dialog-message">Remove this block from the document?</p>
            <div class="splis-ob-dialog-actions">
                <button type="button" class="splis-btn-secondary" data-ob-confirm-cancel>Cancel</button>
                <button type="button" id="ob-confirm-ok" class="splis-btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/ob-maker.js'])
@endpush
