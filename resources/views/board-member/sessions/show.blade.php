@extends('layouts.app')

@section('title', $session->displayTitle().' — My Sessions — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <x-page-header
        class="!mb-6"
        :title="$session->sessionLabel()"
        :subtitle="$session->venue ?: 'Session packet'"
    >
        <x-slot:meta>
            <div class="flex flex-wrap gap-2">
                @if ($canViewOb)
                    <a href="{{ route('ob.document.print', $session) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-sm">
                        <x-icon name="printer" class="h-4 w-4" />
                        View OB print
                    </a>
                @endif
                <a href="{{ route('board-member.sessions.ics', $session) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-sm">
                    <x-icon name="calendar" class="h-4 w-4" />
                    Download calendar ICS
                </a>
                <a href="{{ route('board-member.sessions.index') }}" class="splis-btn-ghost inline-flex items-center gap-2 text-sm">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back to My Sessions
                </a>
            </div>
        </x-slot:meta>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="splis-card overflow-hidden lg:col-span-2">
            <div class="splis-card-header splis-card-header--emphasis">
                <h2 class="splis-card-title">Session Details</h2>
            </div>
            <dl class="splis-card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="splis-detail-label">Date</dt>
                    <dd class="mt-1 font-medium">{{ $session->session_date?->format('l, F j, Y') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Time</dt>
                    <dd class="mt-1 font-medium">{{ $session->formattedSessionTime() ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Venue</dt>
                    <dd class="mt-1 font-medium">{{ $session->venue ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Status</dt>
                    <dd class="mt-1 font-medium">{{ $session->statusLabel() }}</dd>
                </div>
            </dl>
        </div>

        <div class="splis-card overflow-hidden">
            <div class="splis-card-header splis-card-header--emphasis">
                <h2 class="splis-card-title">My Attendance</h2>
            </div>
            <div class="splis-card-body">
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $myAttendanceLabel }}</p>
                <p class="mt-2 text-xs text-slate-500">Attendance is read-only and based on official records.</p>
            </div>
        </div>
    </div>

    <div class="splis-card mb-6 overflow-hidden">
        <div class="splis-card-header splis-card-header--emphasis">
            <h2 class="splis-card-title">My Committee Agendas on this OB</h2>
        </div>
        <div class="splis-card-body">
            @if ($myItemsOnSession->isEmpty())
                <p class="text-sm text-slate-500">No items from your committees were placed on this Order of Business.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($myItemsOnSession as $item)
                        <li>
                            <a href="{{ route('agenda.show', $item) }}" class="splis-link font-medium">
                                {{ $item->displayLabel() }} — {{ \Illuminate\Support\Str::limit($item->title ?: 'Untitled', 110) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="splis-card overflow-hidden">
        <div class="splis-card-header splis-card-header--emphasis">
            <h2 class="splis-card-title">Session Documents</h2>
        </div>
        <div class="splis-card-body">
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($sessionPdfRows as $link)
                    <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-800">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $link['label'] }}</span>
                        @if (($link['kind'] ?? null) === 'maker')
                            <a
                                href="{{ route('ob.sessions.committee-report-summary.print', $session) }}"
                                data-pdf-modal-open
                                data-pdf-viewer="iframe"
                                data-pdf-src="{{ route('ob.sessions.committee-report-summary.print', $session) }}?embed=1"
                                data-pdf-url="{{ route('ob.sessions.committee-report-summary.print', $session) }}"
                                data-pdf-title="{{ $link['label'] }}"
                                class="splis-btn-secondary text-sm"
                            >
                                Preview
                            </a>
                        @elseif (($link['kind'] ?? null) === 'folder')
                            @if ($hasCommitteeReportsFolder ?? false)
                                <button
                                    type="button"
                                    class="splis-btn-secondary text-sm"
                                    data-folder-modal-open
                                    data-folder-modal-target="#bm-committee-reports-folder-modal"
                                >
                                    View folder
                                </button>
                            @else
                                <span class="text-sm text-slate-400">No files</span>
                            @endif
                        @elseif ($link['url'])
                            @if (($link['viewer'] ?? null) === 'external' || ($link['kind'] ?? null) === 'external_link')
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="splis-btn-secondary text-sm">Open link</a>
                            @elseif (($link['viewer'] ?? null) === 'download')
                                <a href="{{ $link['url'] }}" class="splis-btn-secondary text-sm" download>Download</a>
                            @else
                                @include('partials.pdf-modal-trigger', [
                                    'url' => $link['url'],
                                    'viewer' => $link['viewer'],
                                    'title' => $link['label'],
                                    'label' => 'View file',
                                    'class' => 'splis-btn-secondary text-sm',
                                ])
                            @endif
                        @else
                            <span class="text-sm text-slate-400">No file</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @if ($hasCommitteeReportsFolder ?? false)
        @include('partials.document-folder-modal', [
            'modalId' => 'bm-committee-reports-folder-modal',
            'title' => 'Committee Reports',
            'files' => $committeeReportFiles,
            'driveUrl' => $committeeReportsDriveUrl,
        ])
    @endif
</div>
@endsection
