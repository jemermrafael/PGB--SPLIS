@extends('layouts.app')

@section('title', $agenda->displayLabel().' — Agenda — '.config('app.name'))

@section('content')
@php
    use App\Support\AgendaPdfSlot;
@endphp
<div class="max-w-6xl">
    @php
        $agendaSubtitle = $agenda->sender ?: null;
        if ($agenda->versions->isNotEmpty()) {
            $agendaSubtitle = ($agendaSubtitle ? $agendaSubtitle.' · ' : '').'Version '.$agenda->current_version_no;
        }
        $latestObPlacement = $finalObPlacements
            ->filter(fn ($placement) => $placement->legislativeSession?->session_date)
            ->sortByDesc(fn ($placement) => $placement->legislativeSession->session_date)
            ->first();
    @endphp
    <x-page-header
        class="!mb-6"
        :title="'Agenda '.$agenda->displayLabel()"
        :subtitle="$agendaSubtitle"
    >
        <x-slot:badges>
            <span class="splis-agenda-status splis-agenda-status--{{ $agenda->status }}">{{ config('agenda.statuses.'.$agenda->status, $agenda->status) }}</span>
            @if ($agenda->isArchived())
                <span class="splis-badge-linked whitespace-nowrap">Archived</span>
            @endif
            @if ($agenda->is_urgent_request)
                <span class="splis-badge-linked whitespace-nowrap">Urgent Request</span>
            @endif
            @if ($latestObPlacement?->legislativeSession)
                <span class="splis-badge-linked whitespace-nowrap">
                    Scheduled on {{ $latestObPlacement->legislativeSession->session_number ?: 'Order of Business' }} Order of Business {{ $latestObPlacement->legislativeSession->session_date?->format('M d, Y') }}
                </span>
            @endif
            @if ($agenda->hasIncoming())
                <a href="{{ route('incoming.show', $agenda->incomingDocument) }}" class="splis-badge-linked">Incoming linked</a>
            @endif
            @if ($agenda->resolution)
                <a href="{{ route('resolutions.show', $agenda->resolution) }}" class="splis-badge-linked whitespace-nowrap">
                    {{ $agenda->outputConnectionLabel() }} Resolution No.: {{ $agenda->resolution->resolution_no }} · Series {{ $agenda->resolution->series }}
                </a>
            @endif
            @if ($agenda->ordinance)
                <a href="{{ route('ordinances.show', $agenda->ordinance) }}" class="splis-badge-linked whitespace-nowrap">
                    {{ $agenda->outputConnectionLabel() }} {{ $agenda->ordinance->displayNumber() }} · Series {{ $agenda->ordinance->series_year }}
                </a>
            @endif
            @if ($agenda->appropriationOrdinance)
                <a href="{{ route('appropriation-ordinances.show', $agenda->appropriationOrdinance) }}" class="splis-badge-linked whitespace-nowrap">{{ $agenda->outputConnectionLabel() }} Appropriation Ordinance</a>
            @endif
            @if ($agenda->publishedTargetLabel() && ! $agenda->resolution && ! $agenda->ordinance && ! $agenda->appropriationOrdinance)
                <span class="splis-badge-linked">{{ $agenda->outputConnectionLabel() }} {{ $agenda->publishedTargetLabel() }}</span>
            @endif
        </x-slot:badges>
        <x-slot:meta>
            <div class="flex flex-wrap justify-end gap-2">
                @if (auth()->user()?->isBoardMember())
                    <form method="POST" action="{{ route('board-member.watchlist.store') }}">
                        @csrf
                        <input type="hidden" name="watchable_type" value="agenda">
                        <input type="hidden" name="watchable_id" value="{{ $agenda->id }}">
                        <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap">
                            <x-icon name="bell" class="h-4 w-4" />
                            {{ ($isWatchingAgenda ?? false) ? 'Unwatch' : 'Watch' }}
                        </button>
                    </form>
                @endif
                @can('promote', $agenda)
                    @if (config('incoming.enabled', false))
                        <form method="POST" action="{{ route('agenda.promote-incoming', $agenda) }}">
                            @csrf
                            <button type="submit" class="splis-btn-primary inline-flex items-center gap-2 text-nowrap">
                                <x-icon name="inbox" class="h-4 w-4" />
                                Create Incoming
                            </button>
                        </form>
                    @endif
                @endcan
                @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::REQUEST))
                    @include('partials.pdf-modal-trigger', [
                        'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::REQUEST),
                        'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::REQUEST),
                        'title' => 'Request PDF — '.$agenda->displayLabel(),
                        'label' => 'Request PDF',
                        'class' => 'splis-btn-secondary inline-flex items-center gap-2 text-nowrap',
                    ])
                @endif
                @if ($agenda->hasRequestPacketFiles())
                    <button
                        type="button"
                        class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap"
                        data-folder-modal-open
                        data-folder-modal-target="#agenda-request-packet-modal"
                    >
                        <x-icon name="folder" class="h-4 w-4" />
                        Request packet ({{ $agenda->requestFiles->count() }})
                    </button>
                @endif
                @can('update', $agenda)
                    @if ($agenda->missingPdfMirrorSlots() !== [])
                        <form method="POST" action="{{ route('agenda.mirror-pdf', $agenda) }}">
                            @csrf
                            <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap">
                                <x-icon name="download" class="h-4 w-4" />
                                Download from Drive
                            </button>
                        </form>
                    @endif
                @endcan
                @can('update', $agenda)
                    <a href="{{ route('agenda.edit', $agenda) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap">
                        <x-icon name="edit" class="h-4 w-4" />
                        Edit
                    </a>
                @endcan
                @can('archive', $agenda)
                    <form
                        method="POST"
                        action="{{ route('agenda.archive', $agenda) }}"
                        data-confirm-submit
                        data-confirm-title="Archive agenda?"
                        data-confirm-message="Archive &quot;{{ $agenda->displayLabel() }}&quot;? It will be hidden from the Agenda list and can be restored from Archives."
                        data-confirm-label="Archive"
                    >
                        @csrf
                        <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap">
                            <x-icon name="archive" class="h-4 w-4" />
                            Archive
                        </button>
                    </form>
                @endcan
                @can('restoreArchive', $agenda)
                    <form method="POST" action="{{ route('agenda.restore-archive', $agenda) }}">
                        @csrf
                        <button type="submit" class="splis-btn-primary inline-flex items-center gap-2 text-nowrap">
                            <x-icon name="check-circle" class="h-4 w-4" />
                            Restore from Archives
                        </button>
                    </form>
                    <a href="{{ route('admin.archives.index') }}" class="splis-btn-ghost inline-flex items-center gap-2 text-nowrap">
                        <x-icon name="archive" class="h-4 w-4" />
                        Archives
                    </a>
                @endcan
                @can('delete', $agenda)
                    <form
                        method="POST"
                        action="{{ route('agenda.destroy', $agenda) }}"
                        data-confirm-submit
                        data-confirm-title="Delete agenda item?"
                        data-confirm-message="Move &quot;{{ $agenda->displayLabel() }}&quot; to trash? You can restore it later from Trash."
                        data-confirm-label="Delete"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="splis-btn-danger inline-flex items-center gap-2 text-nowrap">
                            <x-icon name="trash" class="h-4 w-4" />
                            Delete
                        </button>
                    </form>
                @endcan
                <a href="{{ route('agenda.index') }}" class="splis-btn-ghost inline-flex items-center gap-2 text-nowrap">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back to list
                </a>
            </div>
        </x-slot:meta>
    </x-page-header>

    @if ($errors->has('promote') || $errors->has('unlink') || $errors->has('version'))
        <div class="splis-alert-error mb-6">{{ $errors->first('promote') ?: $errors->first('unlink') ?: $errors->first('version') }}</div>
    @endif

    <div class="splis-card splis-card-body mb-6">
        @include('agenda.partials.workflow-timeline', ['steps' => $agenda->workflowSteps()])
    </div>

    <div class="splis-detail-with-sidebar">
        <div class="min-w-0 space-y-6">
            <div class="splis-card overflow-hidden">
                <div class="splis-card-header splis-card-header--emphasis">
                    <h2 class="splis-card-title">Intake</h2>
                </div>
                <dl>
                    @if ($agenda->title)
                        <div class="splis-detail-row">
                            <dt class="splis-detail-label">Title</dt>
                            <dd class="splis-detail-value">{{ $agenda->title }}</dd>
                        </div>
                    @endif
                    @foreach ([
                        'Date Received' => $agenda->date_received?->format('M d, Y'),
                        'Time Received' => $agenda->time_received
                            ? \Illuminate\Support\Carbon::parse($agenda->time_received)->format('g:i A')
                            : null,
                        'Prescribed Days' => $agenda->prescribed_days,
                        'Sender' => $agenda->sender,
                    ] as $label => $value)
                        @if ($value !== null && $value !== '')
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $label }}</dt>
                                <dd class="splis-detail-value">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>

            <div class="splis-card overflow-hidden">
                <div class="splis-card-header splis-card-header--emphasis">
                    <h2 class="splis-card-title">Committee</h2>
                </div>
                <dl>
                    @foreach ([
                        'Committee Referred' => $agenda->committee_referred,
                        'Date of Referral' => $agenda->date_of_referral?->format('M d, Y'),
                        'Date of Committee Meeting' => $agenda->date_of_committee_meeting?->format('M d, Y'),
                        'Committee Meeting Minutes' => $agenda->committee_meeting_minutes,
                        'Outcome' => $agenda->outcome,
                    ] as $label => $value)
                        @if ($value !== null && $value !== '')
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $label }}</dt>
                                <dd class="splis-detail-value">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>

            <div class="splis-card overflow-hidden">
                <div class="splis-card-header splis-card-header--emphasis">
                    <h2 class="splis-card-title">Provincial Output</h2>
                </div>
                <dl>
                    @foreach ([
                        'Date Passed' => $agenda->date_passed?->format('M d, Y'),
                        'Date Signed by Gov.' => $agenda->date_signed_by_gov?->format('M d, Y'),
                        'provincial_output_no' => null,
                        'Measure Type' => $agenda->effectiveMeasureType()
                            ? $agenda->measureTypeLabel()
                            : (($agenda->reso_ord_ao_no || $agenda->reso_ord_ao_url) ? 'Not specified (legacy)' : null),
                        'Resolution Title' => $agenda->resolution_title,
                        'Remarks' => $agenda->remarks,
                    ] as $label => $value)
                        @if ($label === 'provincial_output_no' && $agenda->provincialOutputNumberDisplay())
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $agenda->provincialOutputNumberFieldLabel() }}</dt>
                                <dd class="splis-detail-value">
                                    @if ($agenda->publishedTargetRoute())
                                        <a href="{{ $agenda->publishedTargetRoute() }}" class="splis-link font-medium">{{ $agenda->provincialOutputNumberDisplay() }}</a>
                                    @else
                                        {{ $agenda->provincialOutputNumberDisplay() }}
                                    @endif
                                </dd>
                            </div>
                        @elseif ($value !== null && $value !== '')
                            <div class="splis-detail-row">
                                <dt class="splis-detail-label">{{ $label }}</dt>
                                <dd class="splis-detail-value">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="splis-detail-sidebar-column">
            @if ($agenda->hasIncoming() || $agenda->resolution || $agenda->ordinance || $agenda->appropriationOrdinance || $obPlacements->isNotEmpty() || auth()->user()?->can('addToOrderOfBusiness', $agenda) || auth()->user()?->can('linkOutput', $agenda) || auth()->user()?->can('removeFromOrderOfBusiness', $agenda))
                <aside class="splis-card overflow-hidden">
                    <div class="splis-card-header splis-card-header--emphasis">
                        <h2 class="splis-card-title">Connections</h2>
                    </div>
                    <div class="splis-card-body space-y-4">
                        @if ($agenda->hasIncoming() && $agenda->incomingDocument)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="splis-detail-label">Incoming</p>
                                    <a href="{{ route('incoming.show', $agenda->incomingDocument) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $agenda->incomingDocument->displayLabel() }}
                                    </a>
                                </div>
                                @can('unlinkIncoming', $agenda)
                                    <form
                                        method="POST"
                                        action="{{ route('agenda.unlink-incoming', $agenda) }}"
                                        data-confirm-submit
                                        data-confirm-title="Unlink incoming document?"
                                        data-confirm-message="Remove the incoming link? Agenda-created incoming records will be deleted."
                                        data-confirm-label="Unlink"
                                    >
                                        @csrf
                                        <button type="submit" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700">Unlink</button>
                                    </form>
                                @endcan
                            </div>
                        @endif
                        @if ($agenda->resolution)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="splis-detail-label">{{ $agenda->outputConnectionLabel() }}:</p>
                                    <a href="{{ route('resolutions.show', $agenda->resolution) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                        Resolution {{ $agenda->resolution->resolution_no }}
                                    </a>
                                </div>
                                @can('unlinkResolution', $agenda)
                                    <form
                                        method="POST"
                                        action="{{ route('agenda.unlink-resolution', $agenda) }}"
                                        data-confirm-submit
                                        data-confirm-title="Unlink resolution?"
                                        data-confirm-message="Remove the resolution link from this agenda item?"
                                        data-confirm-label="Unlink"
                                    >
                                        @csrf
                                        <button type="submit" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700">Unlink</button>
                                    </form>
                                @endcan
                            </div>
                        @endif
                        @if ($agenda->ordinance)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="splis-detail-label">{{ $agenda->outputConnectionLabel() }}:</p>
                                    <a href="{{ route('ordinances.show', $agenda->ordinance) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $agenda->ordinance->displayNumber() }} ({{ $agenda->ordinance->series_year }})
                                    </a>
                                </div>
                            </div>
                        @endif
                        @if ($agenda->appropriationOrdinance)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="splis-detail-label">{{ $agenda->outputConnectionLabel() }}:</p>
                                    <a href="{{ route('appropriation-ordinances.show', $agenda->appropriationOrdinance) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                        {{ $agenda->appropriationOrdinance->displayNumber() }} ({{ $agenda->appropriationOrdinance->series_year }})
                                    </a>
                                </div>
                            </div>
                        @endif
                        @can('linkOutput', $agenda)
                            <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                                <p class="splis-detail-label">Link provincial output</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    No exact match for
                                    {{ $agenda->measureTypeLabel() }}
                                    {{ $agenda->resoDisplayLabel() ?: ($agenda->reso_ord_ao_no.' / '.$agenda->reso_ord_ao_series) }}.
                                    Choose from the list:
                                </p>
                                @if (($outputLinkCandidates ?? collect())->isEmpty())
                                    <p class="mt-2 text-sm text-slate-500">No candidates found in SPLIS for this series.</p>
                                @else
                                    <form method="POST" action="{{ route('agenda.link-output', $agenda) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <select name="output_id" class="splis-select" required onchange="document.getElementById('agenda-output-type').value = this.options[this.selectedIndex].dataset.type || ''">
                                            <option value="">Select output…</option>
                                            @foreach ($outputLinkCandidates as $candidate)
                                                <option value="{{ $candidate['id'] }}" data-type="{{ $candidate['type'] }}">
                                                    {{ $candidate['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="output_type" id="agenda-output-type" value="{{ $agenda->effectiveMeasureType() }}">
                                        <button type="submit" class="splis-btn-secondary w-full text-sm">Link selected output</button>
                                    </form>
                                @endif
                                @error('link_output')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endcan
                        @include('agenda.partials.ob-placements', ['agenda' => $agenda, 'placements' => $obPlacements])
                        @can('addToOrderOfBusiness', $agenda)
                            <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                                <p class="splis-detail-label">Add to Order of Business</p>
                                @if ($obSessions->isEmpty())
                                    <p class="mt-2 text-sm text-slate-500">
                                        No sessions yet.
                                        <a href="{{ route('ob.sessions.create') }}" class="splis-link">Create a session</a>
                                        to add this agenda item.
                                    </p>
                                @else
                                    <form method="POST" action="{{ route('agenda.add-to-order-of-business', $agenda) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <select name="legislative_session_id" class="splis-select" required>
                                            <option value="">Select session…</option>
                                            @foreach ($obSessions as $obSession)
                                                <option value="{{ $obSession->id }}">{{ $obSession->displayTitle() }}</option>
                                            @endforeach
                                        </select>
                                        <select name="agenda_section" class="splis-select">
                                            @foreach (config('order_of_business.agenda_sections', []) as $value => $label)
                                                <option value="{{ $value }}" @selected($value === 'unassigned_regular')>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="splis-btn-secondary w-full text-sm">Add to OB document</button>
                                    </form>
                                    <p class="mt-2 text-xs text-slate-500">
                                        Or <a href="{{ route('ob.sessions.create') }}" class="splis-link">create a new session</a> and add this item in the OB Maker.
                                    </p>
                                @endif
                            </div>
                        @endcan
                    </div>
                </aside>
            @endif

            <aside class="splis-card splis-agenda-tracking-card overflow-hidden">
                <div class="splis-card-header splis-card-header--emphasis">
                    <h2 class="splis-card-title">Tracking</h2>
                </div>
                <div class="splis-card-body space-y-5">
                    <div>
                        <p class="splis-detail-label">Status</p>
                        <p class="mt-1">
                            <span class="splis-agenda-status splis-agenda-status--{{ $agenda->status }}">
                                {{ config('agenda.statuses.'.$agenda->status, $agenda->status) }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="splis-detail-label">Days left</p>
                        <p class="mt-1">
                            <span class="splis-agenda-days splis-agenda-days--{{ $agenda->daysLeftTone() }} splis-agenda-days--lg">
                                {{ $agenda->days_left_label ?? '—' }}
                            </span>
                        </p>
                    </div>
                    @if ($agenda->due_date)
                        <div>
                            <p class="splis-detail-label">Due date</p>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ $agenda->due_date->format('M d, Y') }}</p>
                        </div>
                    @endif
                    @if ($agenda->prescribed_days !== null)
                        <div>
                            <p class="splis-detail-label">Prescribed days</p>
                            <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ $agenda->prescribed_days }}</p>
                        </div>
                    @endif
                    @if ($progress = $agenda->deadlineProgressPercent())
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                                <span>Deadline progress</span>
                                <span>{{ $progress }}%</span>
                            </div>
                            <div class="splis-agenda-progress">
                                <div class="splis-agenda-progress-bar splis-agenda-progress-bar--{{ $agenda->daysLeftTone() }}" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </aside>

            @if ($agenda->hasRequestPacketFiles() || auth()->user()?->can('update', $agenda))
                <div class="splis-card overflow-hidden">
                    <div class="splis-card-header splis-card-header--emphasis">
                        <h2 class="splis-card-title">Request packet</h2>
                        <p class="splis-card-subtitle">Multiple files with folder names (e.g. FOR RECOGNITION)</p>
                    </div>
                    <div class="splis-card-body space-y-4">
                        @if ($agenda->hasRequestPacketFiles())
                            <div class="space-y-3">
                                @foreach ($agenda->requestFilesGroupedByFolder() as $folderLabel => $folderFiles)
                                    <div>
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $folderLabel }}</p>
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($folderFiles as $file)
                                                <li class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="min-w-0 truncate" title="{{ $file->original_filename }}">{{ $file->original_filename }}</span>
                                                    @include('partials.pdf-modal-trigger', [
                                                        'url' => $file->publicUrl(),
                                                        'viewer' => $file->viewerMode(),
                                                        'title' => $file->original_filename,
                                                        'label' => 'View',
                                                        'class' => 'splis-btn-ghost text-sm',
                                                    ])
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-500">No request packet files yet. Upload files below (leave folder blank for the primary Request PDF).</p>
                        @endif

                        <p class="text-xs text-slate-500">Files uploaded with an empty folder name go in Root and become the primary Request PDF.</p>

                        @can('update', $agenda)
                            <form method="POST" action="{{ route('agenda.request-files.store', $agenda) }}" enctype="multipart/form-data" class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                                @csrf
                                <div>
                                    <label class="splis-label" for="agenda-request-folder">Folder name (optional)</label>
                                    <input type="text" name="relative_folder" id="agenda-request-folder" class="splis-input" placeholder="FOR ACCREDITATION">
                                    <p class="mt-1 text-xs text-slate-500">Leave blank for Root — that PDF is used as the Request PDF.</p>
                                </div>
                                <div>
                                    <label class="splis-label" for="agenda-request-packet-files">Files</label>
                                    <input type="file" name="request_packet_files[]" id="agenda-request-packet-files" class="splis-input" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,application/pdf">
                                </div>
                                <button type="submit" class="splis-btn-secondary w-full text-sm">Upload to request packet</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endif

            @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::COMMITTEE_REPORT) || $agenda->pdfPublicUrlFor(AgendaPdfSlot::RESO_ORD_AO) || $agenda->pdfPublicUrlFor(AgendaPdfSlot::JOURNAL) || $agenda->pdfPublicUrlFor(AgendaPdfSlot::MINUTES) || $agenda->isPublished())
                <div class="splis-card overflow-hidden">
                    <div class="splis-card-header splis-card-header--emphasis">
                        <h2 class="splis-card-title">Documents</h2>
                    </div>
                    <div class="splis-card-body flex flex-col gap-2">
                        @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::COMMITTEE_REPORT))
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::COMMITTEE_REPORT),
                                'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::COMMITTEE_REPORT),
                                'title' => 'Committee Report — '.$agenda->displayLabel(),
                                'label' => 'Committee Report',
                            ])
                        @endif
                        @if ($agenda->isPublished() && $agenda->publishedTargetRoute())
                            <a href="{{ $agenda->publishedTargetRoute() }}" class="splis-btn-secondary text-sm">{{ $agenda->splisOutputButtonLabel() }}</a>
                        @elseif ($agenda->pdfPublicUrlFor(AgendaPdfSlot::RESO_ORD_AO))
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::RESO_ORD_AO),
                                'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::RESO_ORD_AO),
                                'title' => $agenda->legacyOutputPdfButtonLabel().' — '.$agenda->displayLabel(),
                                'label' => $agenda->legacyOutputPdfButtonLabel(),
                            ])
                        @endif
                        @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::JOURNAL))
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::JOURNAL),
                                'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::JOURNAL),
                                'title' => 'Journal of Proceedings — '.$agenda->displayLabel(),
                                'label' => 'Journal of Proceedings',
                                'class' => 'splis-btn-secondary text-sm inline-flex items-center justify-center gap-2',
                            ])
                        @endif
                        @if ($agenda->pdfPublicUrlFor(AgendaPdfSlot::MINUTES))
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(AgendaPdfSlot::MINUTES),
                                'viewer' => $agenda->pdfViewerModeFor(AgendaPdfSlot::MINUTES),
                                'title' => 'Minutes of Session — '.$agenda->displayLabel(),
                                'label' => 'Minutes of Session',
                                'class' => 'splis-btn-secondary text-sm inline-flex items-center justify-center gap-2',
                            ])
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('agenda.partials.version-history', ['agenda' => $agenda])

    @include('partials.document-folder-modal', [
        'modalId' => 'agenda-request-packet-modal',
        'title' => 'Request packet — '.$agenda->displayLabel(),
        'grouped' => $agenda->requestFilesGroupedByFolder(),
        'driveUrl' => $agenda->request_pdf_url,
        'canManage' => auth()->user()?->can('update', $agenda),
        'agenda' => $agenda,
    ])

    @include('agenda.partials.splis-activity-logs', [
        'splisActivityLogs' => $splisActivityLogs ?? collect(),
        'obPlacementCount' => $obPlacementCount ?? 0,
        'emphasisHeaders' => true,
    ])

    @include('partials.detail-prev-next', [
        'previous' => $previousAgenda ?? null,
        'next' => $nextAgenda ?? null,
        'previousUrl' => ($previousAgenda ?? null) ? route('agenda.show', $previousAgenda) : null,
        'nextUrl' => ($nextAgenda ?? null) ? route('agenda.show', $nextAgenda) : null,
        'previousLabel' => isset($previousAgenda) ? $previousAgenda->displayLabel() : null,
        'nextLabel' => isset($nextAgenda) ? $nextAgenda->displayLabel() : null,
        'label' => 'Agenda navigation',
    ])
</div>
@endsection
