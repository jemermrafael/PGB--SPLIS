@extends('layouts.app')

@php
    $isEdit = $agenda->exists;
    $timeValue = old('time_received');
    if ($timeValue === null && $agenda->time_received) {
        $timeValue = \Illuminate\Support\Str::of($agenda->time_received)->substr(0, 5);
    }
@endphp

@section('title', ($isEdit ? 'Edit Agenda' : 'New Agenda').' — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit Agenda Item' : 'New Agenda Item' }}</h1>
            <p class="splis-page-subtitle">Record intake, committee action, and provincial output.</p>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('agenda.update', $agenda) : route('agenda.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="splis-card splis-card-body space-y-4">
            <div>
                <h2 class="splis-form-section-title">Intake</h2>
                <p class="splis-form-section-subtitle">Receipt details and sender information.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="splis-label" for="tracking_no">Tracking no.</label>
                    <input type="text" name="tracking_no" id="tracking_no" value="{{ old('tracking_no', $agenda->tracking_no) }}" class="splis-input" placeholder="{{ old('is_urgent_request', $agenda->is_urgent_request) ? 'Pending assignment by SP Secretary' : '001' }}">
                    @if (old('is_urgent_request', $agenda->is_urgent_request) && ! old('tracking_no', $agenda->tracking_no))
                        <p class="mt-1 text-xs text-slate-500">Leave blank until the SP Secretary assigns an official agenda number.</p>
                    @endif
                </div>
                <div>
                    <label class="splis-label" for="request_pdf">Request file (upload)</label>
                    <input type="file" name="request_pdf" id="request_pdf" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp" class="splis-input">
                    @if ($isEdit && $agenda->hasLocalPdfFor(App\Support\AgendaPdfSlot::REQUEST))
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Local file: <code>{{ $agenda->request_pdf_path }}</code>
                            — uploading replaces it.
                        </p>
                    @endif
                </div>
                <div>
                    <label class="splis-label" for="request_pdf_url">Request PDF URL (fallback)</label>
                    <input type="url" name="request_pdf_url" id="request_pdf_url" value="{{ old('request_pdf_url', $agenda->request_pdf_url) }}" class="splis-input" placeholder="Google Drive link">
                    <p class="mt-1 text-xs text-slate-500">Used when no local file is present. Can be mirrored from the agenda page or Data Sync queue.</p>
                </div>
                <div>
                    <label class="splis-label" for="date_received">Date received</label>
                    <input type="date" name="date_received" id="date_received" value="{{ old('date_received', $agenda->date_received?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="time_received">Time received</label>
                    <input type="time" name="time_received" id="time_received" value="{{ $timeValue }}" class="splis-input">
                </div>
                @include('partials.combobox-field', [
                    'name' => 'sender',
                    'id' => 'sender',
                    'label' => 'Sender',
                    'value' => old('sender', $agenda->sender),
                    'options' => $senders,
                    'placeholder' => 'Search municipality or office…',
                ])
                <div>
                    <label class="splis-label" for="prescribed_days">Prescribed days</label>
                    <select name="prescribed_days" id="prescribed_days" class="splis-select">
                        <option value="">—</option>
                        @foreach ($prescribedDays as $days)
                            <option value="{{ $days }}" @selected((string) old('prescribed_days', $agenda->prescribed_days) === (string) $days)>{{ $days === 0 ? '0 (no due date)' : $days }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="splis-label" for="status">Status</label>
                    <select name="status" id="status" class="splis-select">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $agenda->status ?: 'pending') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input
                            type="checkbox"
                            name="is_urgent_request"
                            value="1"
                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            @checked(old('is_urgent_request', $agenda->is_urgent_request))
                        >
                        <span>Urgent Request</span>
                    </label>
                </div>
            </div>

            <div id="agenda-deadline-preview" class="splis-agenda-deadline-preview" data-preview-url="{{ $deadlinePreviewUrl }}" data-preview-tone="{{ $agenda->daysLeftTone() }}">
                <p class="splis-agenda-deadline-preview-title">Deadline preview</p>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="splis-detail-label">Due date</p>
                        <p class="mt-1 font-medium text-slate-900 dark:text-slate-100" data-preview-due-date>
                            {{ $agenda->due_date?->format('M d, Y') ?? 'No due date' }}
                        </p>
                    </div>
                    <div>
                        <p class="splis-detail-label">Days left</p>
                        <p class="mt-1">
                            <span class="splis-agenda-days splis-agenda-days--{{ $agenda->daysLeftTone() }} splis-agenda-days--lg" data-preview-days-left>
                                {{ $agenda->days_left_label ?? '—' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <div>
                <label class="splis-label" for="title">Title</label>
                <textarea name="title" id="title" rows="5" class="splis-textarea">{{ old('title', $agenda->title) }}</textarea>
            </div>
        </div>

        <div class="splis-card splis-card-body space-y-4">
            <div>
                <h2 class="splis-form-section-title">Committee</h2>
                <p class="splis-form-section-subtitle">Referral and committee action tracking.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    @include('partials.combobox-field', [
                        'name' => 'committee_referred',
                        'id' => 'committee_referred',
                        'label' => 'Committee referred',
                        'value' => old('committee_referred', $agenda->committee_referred),
                        'options' => $committees,
                        'placeholder' => 'Search committees…',
                    ])
                </div>
                <div>
                    <label class="splis-label" for="date_of_referral">Date of referral</label>
                    <input type="date" name="date_of_referral" id="date_of_referral" value="{{ old('date_of_referral', $agenda->date_of_referral?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="date_of_committee_meeting">Date of committee meeting</label>
                    <input type="date" name="date_of_committee_meeting" id="date_of_committee_meeting" value="{{ old('date_of_committee_meeting', $agenda->date_of_committee_meeting?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="committee_meeting_minutes">Committee meeting minutes</label>
                    <input type="text" name="committee_meeting_minutes" id="committee_meeting_minutes" value="{{ old('committee_meeting_minutes', $agenda->committee_meeting_minutes) }}" class="splis-input">
                </div>
                @include('partials.combobox-field', [
                    'name' => 'outcome',
                    'id' => 'outcome',
                    'label' => 'Outcome',
                    'value' => old('outcome', $agenda->outcome),
                    'options' => $outcomes,
                    'placeholder' => 'Search or type outcome…',
                ])
                <div class="md:col-span-2">
                    <label class="splis-label" for="committee_report_pdf">Committee report file (upload)</label>
                    <input type="file" name="committee_report_pdf" id="committee_report_pdf" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp" class="splis-input">
                    @if ($isEdit && $agenda->pdfPublicUrlFor(App\Support\AgendaPdfSlot::COMMITTEE_REPORT))
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @include('partials.pdf-modal-trigger', [
                                'url' => $agenda->pdfPublicUrlFor(App\Support\AgendaPdfSlot::COMMITTEE_REPORT),
                                'viewer' => $agenda->pdfViewerModeFor(App\Support\AgendaPdfSlot::COMMITTEE_REPORT),
                                'title' => 'Committee Report — '.$agenda->displayLabel(),
                                'label' => 'View current file',
                                'class' => 'splis-btn-secondary inline-flex items-center gap-2 text-sm',
                            ])
                            @if ($agenda->hasLocalPdfFor(App\Support\AgendaPdfSlot::COMMITTEE_REPORT))
                                <span class="text-xs text-slate-500 dark:text-slate-400">Uploading replaces the current file.</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="committee_report_url">Committee report link (fallback)</label>
                    <input type="url" name="committee_report_url" id="committee_report_url" value="{{ old('committee_report_url', $agenda->committee_report_url) }}" class="splis-input">
                </div>
            </div>
        </div>

        <div id="agenda-provincial-output" class="splis-card splis-card-body space-y-4 splis-agenda-output">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="splis-form-section-title">Provincial Output</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Required when status is Done. Choose the measure type first — fields below will adjust.</p>
                </div>
                @if ($isEdit && $agenda->isPublished() && $agenda->publishedTargetRoute())
                    <a href="{{ $agenda->publishedTargetRoute() }}" class="splis-badge-linked shrink-0">
                        Linked to {{ $agenda->publishedTargetLabel() }}
                    </a>
                @endif
            </div>

            <div>
                <label class="splis-label" for="reso_ord_ao_type">Output measure type</label>
                <select name="reso_ord_ao_type" id="reso_ord_ao_type" class="splis-select">
                    <option value="">Select measure type…</option>
                    @foreach ($measureTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('reso_ord_ao_type', $agenda->effectiveMeasureType()) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="splis-label" for="reso_ord_ao_no">Reso./Ord./AO no.</label>
                    <input type="text" name="reso_ord_ao_no" id="reso_ord_ao_no" value="{{ old('reso_ord_ao_no', $agenda->reso_ord_ao_no) }}" class="splis-input" placeholder="218">
                </div>
                <div>
                    <label class="splis-label" for="reso_ord_ao_series">Series (year)</label>
                    <input type="number" name="reso_ord_ao_series" id="reso_ord_ao_series" value="{{ old('reso_ord_ao_series', $agenda->reso_ord_ao_series) }}" min="1900" max="2100" class="splis-input" placeholder="{{ now()->year }}">
                </div>
                <div>
                    <label class="splis-label" for="date_passed">Date passed</label>
                    <input type="date" name="date_passed" id="date_passed" value="{{ old('date_passed', $agenda->date_passed?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="date_signed_by_gov">Date signed by Gov.</label>
                    <input type="date" name="date_signed_by_gov" id="date_signed_by_gov" value="{{ old('date_signed_by_gov', $agenda->date_signed_by_gov?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="resolution_title">Output title</label>
                    <textarea name="resolution_title" id="resolution_title" rows="4" class="splis-textarea">{{ old('resolution_title', $agenda->resolution_title) }}</textarea>
                </div>
                <div class="md:col-span-2" data-measure-panel="resolution">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Resolution output can include journal and session minutes links.</p>
                </div>
                <div class="md:col-span-2" data-measure-panel="ordinance">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Ordinance output will be published or linked in SPLIS ordinances when saved as Done.</p>
                </div>
                <div class="md:col-span-2" data-measure-panel="appropriation_ordinance">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Appropriation Ordinance output will be published or linked in SPLIS when saved as Done.</p>
                </div>
                <div data-resolution-only class="md:col-span-2 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="splis-label" for="journal_pdf">Journal file (upload)</label>
                        <input type="file" name="journal_pdf" id="journal_pdf" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp" class="splis-input">
                        @if ($isEdit && $agenda->hasLocalPdfFor(App\Support\AgendaPdfSlot::JOURNAL))
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Local file: <code>{{ $agenda->journal_pdf_path }}</code>
                                — uploading replaces it.
                            </p>
                        @endif
                    </div>
                    <div>
                        <label class="splis-label" for="minutes_pdf">Minutes file (upload)</label>
                        <input type="file" name="minutes_pdf" id="minutes_pdf" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp" class="splis-input">
                        @if ($isEdit && $agenda->hasLocalPdfFor(App\Support\AgendaPdfSlot::MINUTES))
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Local file: <code>{{ $agenda->minutes_pdf_path }}</code>
                                — uploading replaces it.
                            </p>
                        @endif
                    </div>
                    <div>
                        <label class="splis-label" for="journal_url">Journal of proceedings (fallback)</label>
                        <input type="url" name="journal_url" id="journal_url" value="{{ old('journal_url', $agenda->journal_url) }}" class="splis-input">
                    </div>
                    <div>
                        <label class="splis-label" for="minutes_url">Minutes of session (fallback)</label>
                        <input type="url" name="minutes_url" id="minutes_url" value="{{ old('minutes_url', $agenda->minutes_url) }}" class="splis-input">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="reso_ord_ao_pdf">Output file (upload)</label>
                    <input type="file" name="reso_ord_ao_pdf" id="reso_ord_ao_pdf" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp" class="splis-input">
                    @if ($isEdit && $agenda->hasLocalPdfFor(App\Support\AgendaPdfSlot::RESO_ORD_AO))
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Local file: <code>{{ $agenda->reso_ord_ao_pdf_path }}</code>
                            — uploading replaces it.
                        </p>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="reso_ord_ao_url">Output PDF URL (legacy GDrive fallback)</label>
                    <input type="url" name="reso_ord_ao_url" id="reso_ord_ao_url" value="{{ old('reso_ord_ao_url', $agenda->reso_ord_ao_url) }}" class="splis-input" placeholder="Optional when linked in SPLIS">
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="remarks">Status / remarks</label>
                    <textarea name="remarks" id="remarks" rows="2" class="splis-textarea">{{ old('remarks', $agenda->remarks) }}</textarea>
                </div>
            </div>
        </div>

        <div class="splis-form-actions">
            <button type="submit" class="splis-btn-primary">Save</button>
            <a href="{{ $isEdit ? route('agenda.show', $agenda) : route('agenda.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
