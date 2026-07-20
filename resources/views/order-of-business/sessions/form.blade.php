@extends('layouts.app')

@php
    $isEdit = $session->exists;
    $timeValue = old('session_time');
    if ($timeValue === null) {
        $timeValue = $session->formattedSessionTime();
    }
@endphp

@section('title', ($isEdit ? 'Edit Session' : 'New Session').' — Order of Business — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit Legislative Session' : 'New legislative session' }}</h1>
            <p class="splis-page-subtitle">
                @if ($isEdit)
                    Session details for the Order of Business document.
                @else
                    Create the session first, then add agenda items in the OB Maker.
                @endif
            </p>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('ob.sessions.update', $session) : route('ob.sessions.store') }}" @if ($isEdit) enctype="multipart/form-data" @endif class="space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="splis-card splis-card-body space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="splis-label" for="session_date">Session date</label>
                    <input type="date" name="session_date" id="session_date" value="{{ old('session_date', $session->session_date?->format('Y-m-d')) }}" class="splis-input" required>
                </div>
                <div>
                    <label class="splis-label" for="session_time">Session time</label>
                    <input type="time" name="session_time" id="session_time" value="{{ $timeValue }}" class="splis-input">
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="session_number">Session number / label</label>
                    <input type="text" name="session_number" id="session_number" value="{{ old('session_number', $session->session_number) }}" class="splis-input" placeholder="25th Regular Session">
                </div>
                <div>
                    <label class="splis-label" for="session_kind">Session kind</label>
                    <select name="session_kind" id="session_kind" class="splis-select" required>
                        @foreach ($sessionKinds as $value => $label)
                            <option value="{{ $value }}" @selected(old('session_kind', $session->session_kind ?: 'regular') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="splis-label" for="status">Status</label>
                    <select name="status" id="status" class="splis-select" required>
                        @foreach ($sessionStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $session->status ?: 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="venue">Venue</label>
                    <input type="text" name="venue" id="venue" value="{{ old('venue', $session->venue) }}" class="splis-input" placeholder="The Bunker, Capitol Compound, Balanga City">
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="prior_session_id">Prior session (for minutes approval)</label>
                    <select name="prior_session_id" id="prior_session_id" class="splis-select">
                        <option value="">— None —</option>
                        @foreach ($priorSessions as $prior)
                            <option value="{{ $prior->id }}" @selected((string) old('prior_session_id', $session->prior_session_id) === (string) $prior->id)>
                                {{ $prior->displayTitle() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="notes">Notes</label>
                    <textarea name="notes" id="notes" rows="3" class="splis-textarea">{{ old('notes', $session->notes) }}</textarea>
                </div>
            </div>
        </div>

        @if ($isEdit)
            <div class="splis-card splis-card-body space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Session Documents</h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Upload PDFs locally or keep a Google Drive link as fallback. Local files are used first on the session page.</p>
                    </div>
                    @if ($session->missingMirrorSessionPdfSlots() !== [])
                        <button type="submit" form="mirror-session-pdfs-form" class="splis-btn-secondary inline-flex items-center gap-2 whitespace-nowrap">
                            <x-icon name="download" class="h-4 w-4" />
                            Download linked PDFs
                        </button>
                    @endif
                </div>
                <div class="grid grid-cols-1 gap-6">
                    @foreach ($session->sessionPdfLinkRows() as $link)
                        @if ($link['kind'] === 'folder')
                            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $link['label'] }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Upload one or more committee report PDFs. They are stored locally in a folder for this session.</p>

                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="splis-label" for="committee_report_files">Upload PDF files</label>
                                        <input
                                            type="file"
                                            name="committee_report_files[]"
                                            id="committee_report_files"
                                            accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp"
                                            class="splis-input"
                                            multiple
                                        >
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">You can select multiple files. New uploads are added to the folder.</p>
                                    </div>

                                    @if ($session->committeeReportFiles->isNotEmpty())
                                        <div>
                                            <p class="splis-label">Uploaded files ({{ $session->committeeReportFiles->count() }})</p>
                                            <ul class="mt-2 divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                                @foreach ($session->committeeReportFiles as $file)
                                                    <li class="flex items-center justify-between gap-3 px-3 py-2.5">
                                                        <span class="min-w-0 truncate text-sm text-slate-700 dark:text-slate-300" title="{{ $file->original_filename }}">
                                                            {{ $file->original_filename }}
                                                        </span>
                                                        <div class="flex shrink-0 items-center gap-2">
                                                            @include('partials.pdf-modal-trigger', [
                                                                'url' => $file->publicUrl(),
                                                                'viewer' => $file->viewerMode(),
                                                                'title' => $file->original_filename,
                                                                'label' => 'View',
                                                                'class' => 'splis-btn-secondary inline-flex items-center gap-1.5 text-xs',
                                                            ])
                                                            <form
                                                                method="POST"
                                                                action="{{ route('ob.sessions.committee-report-file.destroy', [$session, $file]) }}"
                                                                data-confirm-submit
                                                                data-confirm-title="Remove this file?"
                                                                data-confirm-message="This removes the local copy only. The Google Drive folder link is not affected."
                                                                data-confirm-label="Remove"
                                                            >
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="splis-btn-ghost inline-flex items-center gap-1.5 !px-2 !py-1 text-xs text-red-600 dark:text-red-400">
                                                                    <x-icon name="trash" class="h-3.5 w-3.5" />
                                                                    Remove
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <div>
                                        <label class="splis-label" for="{{ $link['field'] }}">Google Drive folder link (fallback)</label>
                                        <input
                                            type="url"
                                            name="{{ $link['field'] }}"
                                            id="{{ $link['field'] }}"
                                            value="{{ old($link['field'], $session->{$link['field']}) }}"
                                            class="splis-input"
                                            placeholder="https://drive.google.com/drive/folders/..."
                                        >
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Optional external folder when reports are not uploaded here.</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            @php
                                $uploadField = App\Support\SessionPdfSlot::config($link['field'])['upload'];
                                $pathColumn = App\Support\SessionPdfSlot::config($link['field'])['path'];
                            @endphp
                            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $link['label'] }}</h3>
                                <div class="mt-4 grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="splis-label" for="{{ $uploadField }}">{{ $link['label'] }} (upload)</label>
                                        <input
                                            type="file"
                                            name="{{ $uploadField }}"
                                            id="{{ $uploadField }}"
                                            accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp"
                                            class="splis-input"
                                        >
                                        @if ($link['mirrored'])
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                Local file: <code>{{ $session->{$pathColumn} }}</code> — uploading replaces it.
                                            </p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="splis-label" for="{{ $link['field'] }}">{{ $link['label'] }} URL (fallback)</label>
                                        <input
                                            type="url"
                                            name="{{ $link['field'] }}"
                                            id="{{ $link['field'] }}"
                                            value="{{ old($link['field'], $session->{$link['field']}) }}"
                                            class="splis-input"
                                            placeholder="https://"
                                        >
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Used when no local file is present. Can be mirrored using the button above.</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @unless ($isEdit)
            <div class="splis-card splis-card-body">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    After creating the session, you will be taken to the OB Maker where you can add agenda items into each section.
                </p>
            </div>
        @endunless

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon :name="$isEdit ? 'edit' : 'plus'" class="h-4 w-4" :stroke-width="$isEdit ? '1.75' : '2'" />
                {{ $isEdit ? 'Save changes' : 'Create session' }}
            </button>
            <a href="{{ $isEdit ? route('ob.sessions.show', $session) : route('ob.sessions.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>

    @if ($isEdit)
        @if ($session->missingMirrorSessionPdfSlots() !== [])
            <form id="mirror-session-pdfs-form" method="POST" action="{{ route('ob.sessions.mirror-pdfs', $session) }}">
                @csrf
            </form>
        @endif
        @can('delete', $session)
            <div class="mt-6 flex justify-end">
                <form
                    method="POST"
                    action="{{ route('ob.sessions.destroy', $session) }}"
                    data-confirm-submit
                    data-confirm-title="Move Order of Business session to trash?"
                    data-confirm-message="Move this Order of Business session to trash? Superadmin can restore from Trash."
                    data-confirm-label="Delete"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="splis-btn-danger inline-flex items-center gap-2">
                        <x-icon name="trash" class="h-4 w-4" />
                        Delete
                    </button>
                </form>
            </div>
        @endcan
    @endif
</div>
@endsection
