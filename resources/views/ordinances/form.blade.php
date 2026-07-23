@extends('layouts.app')

@php
    $isEdit = $ordinance->exists;
    $classifications = config('ordinances.classifications', []);
@endphp

@section('title', ($isEdit ? 'Edit Ordinance' : 'New Ordinance').' — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit Ordinance' : 'New Ordinance' }}</h1>
            <p class="splis-page-subtitle">Provincial Ordinance record — enactment through publication and effectivity.</p>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('ordinances.update', $ordinance) : route('ordinances.store') }}" enctype="multipart/form-data" class="splis-card splis-card-body space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="splis-label" for="ordinance_no">Ordinance no.</label>
                <input type="number" name="ordinance_no" id="ordinance_no" value="{{ old('ordinance_no', $ordinance->ordinance_no) }}" min="1" required class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="series_year">Series year</label>
                <input type="number" name="series_year" id="series_year" value="{{ old('series_year', $ordinance->series_year) }}" min="1900" max="2100" required class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="classification">Classification</label>
                <select name="classification" id="classification" class="splis-input">
                    <option value="">— Select —</option>
                    @foreach ($classifications as $option)
                        <option value="{{ $option }}" @selected(old('classification', $ordinance->classification) === $option)>{{ $option }}</option>
                    @endforeach
                    @php $currentClassification = old('classification', $ordinance->classification); @endphp
                    @if ($currentClassification && ! in_array($currentClassification, $classifications, true))
                        <option value="{{ $currentClassification }}" selected>{{ $currentClassification }}</option>
                    @endif
                </select>
            </div>
        </div>

        <div>
            <label class="splis-label" for="title">Title</label>
            <input type="text" name="title" id="title" value="{{ old('title', $ordinance->title) }}" maxlength="500" class="splis-input" placeholder="Eminent Domain for Road Right-of-Way…">
            <p class="mt-1.5 text-xs text-slate-400">Shown as “Ord. No. XX - Title” on the ordinance page.</p>
        </div>

        <div>
            <label class="splis-label" for="subject">Subject</label>
            <textarea name="subject" id="subject" rows="4" class="splis-input">{{ old('subject', $ordinance->subject) }}</textarea>
        </div>

        <div
            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-800/50"
            data-ordinance-attribution
            data-initial-mode="{{ ($selectedAuthorIds !== [] || $selectedSponsorIds !== []) ? 'separate' : 'combined' }}"
        >
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Board Member attribution</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Optional — {{ $rosterTerm->label }} roster.</p>
                </div>
                <div class="splis-attribution-toggle" role="group" aria-label="Attribution type">
                    <button type="button" class="splis-attribution-toggle-btn" data-attribution-mode="combined" aria-pressed="false">Authored &amp; Sponsored</button>
                    <button type="button" class="splis-attribution-toggle-btn" data-attribution-mode="separate" aria-pressed="false">Author / Sponsor</button>
                </div>
            </div>

            <div data-attribution-panel="combined">
                @include('ordinances.partials.board-member-picker', [
                    'name' => 'authored_sponsored_member_ids',
                    'label' => 'Authored & Sponsored by',
                    'showLabel' => false,
                    'selectedIds' => $selectedAuthoredSponsoredIds,
                    'boardMembers' => $boardMembers,
                ])
            </div>

            <div class="hidden" data-attribution-panel="separate">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @include('ordinances.partials.board-member-picker', [
                        'name' => 'author_member_ids',
                        'label' => 'Authored by',
                        'selectedIds' => $selectedAuthorIds,
                        'boardMembers' => $boardMembers,
                    ])
                    @include('ordinances.partials.board-member-picker', [
                        'name' => 'sponsor_member_ids',
                        'label' => 'Sponsored by',
                        'selectedIds' => $selectedSponsorIds,
                        'boardMembers' => $boardMembers,
                    ])
                </div>
            </div>
        </div>

        <div>
            <label class="splis-label" for="publication_status">Publication status</label>
            <select name="publication_status" id="publication_status" class="splis-input">
                <option value="">— Not set —</option>
                @foreach (App\Enums\OrdinancePublicationStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('publication_status', $ordinance->publication_status?->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Matches the spreadsheet legend: cyan = published, yellow = for publication.</p>
        </div>

        <div>
            <label class="splis-label" for="pdf">Ordinance PDF (upload)</label>
            <input type="file" name="pdf" id="pdf" accept="application/pdf,.pdf" class="splis-input">
            @if ($isEdit && $ordinance->hasLocalPdf())
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Local file: <code>{{ $ordinance->pdf_path }}</code>
                    — uploading replaces it.
                </p>
            @endif
        </div>

        <div>
            <label class="splis-label" for="pdf_url">Ordinance PDF URL (fallback)</label>
            <input type="url" name="pdf_url" id="pdf_url" value="{{ old('pdf_url', $ordinance->pdf_url) }}" class="splis-input" placeholder="Google Drive link">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Used when no local file is present. Can be mirrored to local storage from the Ordinance page.</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-800/50">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Key dates</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="splis-label" for="date_enacted">Date enacted</label>
                    <input type="date" name="date_enacted" id="date_enacted" value="{{ old('date_enacted', $ordinance->date_enacted?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="date_approved">Date approved</label>
                    <input type="date" name="date_approved" id="date_approved" value="{{ old('date_approved', $ordinance->date_approved?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="date_posted">Posted in conspicuous places</label>
                    <input type="date" name="date_posted" id="date_posted" value="{{ old('date_posted', $ordinance->date_posted?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="date_published_newspaper">Published in newspaper</label>
                    <input type="date" name="date_published_newspaper" id="date_published_newspaper" value="{{ old('date_published_newspaper', $ordinance->date_published_newspaper?->format('Y-m-d')) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="effectivity_date">Effectivity date</label>
                    <input type="date" name="effectivity_date" id="effectivity_date" value="{{ old('effectivity_date', $ordinance->effectivity_date?->format('Y-m-d')) }}" class="splis-input">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-800/50">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Means of verification</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="splis-label" for="mov_bulletin">Bulletin</label>
                    <textarea name="mov_bulletin" id="mov_bulletin" rows="2" class="splis-input">{{ old('mov_bulletin', $ordinance->mov_bulletin) }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="mov_bulletin_pdf">Bulletin file (upload)</label>
                    <input type="file" name="mov_bulletin_pdf" id="mov_bulletin_pdf" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp" class="splis-input">
                    @if ($isEdit && $ordinance->hasLocalPdfType(App\Support\OrdinancePdfType::BULLETIN))
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Local file: <code>{{ $ordinance->mov_bulletin_pdf_path }}</code>
                            — uploading replaces it.
                        </p>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="mov_bulletin_url">Bulletin file URL (fallback)</label>
                    <input type="url" name="mov_bulletin_url" id="mov_bulletin_url" value="{{ old('mov_bulletin_url', $ordinance->mov_bulletin_url) }}" class="splis-input" placeholder="Google Drive link">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">PDF or image (e.g. photo of posted bulletin). Used when no uploaded or mirrored local file is present.</p>
                </div>
                <div>
                    <label class="splis-label" for="mov_certification">Certification</label>
                    <input type="text" name="mov_certification" id="mov_certification" value="{{ old('mov_certification', $ordinance->mov_certification) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="mov_certification_pdf">Certification file (upload)</label>
                    <input type="file" name="mov_certification_pdf" id="mov_certification_pdf" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp" class="splis-input">
                    @if ($isEdit && $ordinance->hasLocalPdfType(App\Support\OrdinancePdfType::CERTIFICATION))
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Local file: <code>{{ $ordinance->mov_certification_pdf_path }}</code>
                        </p>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="mov_certification_url">Certification file URL (fallback)</label>
                    <input type="url" name="mov_certification_url" id="mov_certification_url" value="{{ old('mov_certification_url', $ordinance->mov_certification_url) }}" class="splis-input" placeholder="Google Drive link">
                </div>
                <div>
                    <label class="splis-label" for="mov_newspaper">Newspaper</label>
                    <input type="text" name="mov_newspaper" id="mov_newspaper" value="{{ old('mov_newspaper', $ordinance->mov_newspaper) }}" class="splis-input">
                </div>
                <div>
                    <label class="splis-label" for="mov_newspaper_pdf">Newspaper file (upload)</label>
                    <input type="file" name="mov_newspaper_pdf" id="mov_newspaper_pdf" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp" class="splis-input">
                    @if ($isEdit && $ordinance->hasLocalPdfType(App\Support\OrdinancePdfType::NEWSPAPER))
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Local file: <code>{{ $ordinance->mov_newspaper_pdf_path }}</code>
                        </p>
                    @endif
                </div>
                <div class="md:col-span-2">
                    <label class="splis-label" for="mov_newspaper_url">Newspaper file URL (fallback)</label>
                    <input type="url" name="mov_newspaper_url" id="mov_newspaper_url" value="{{ old('mov_newspaper_url', $ordinance->mov_newspaper_url) }}" class="splis-input" placeholder="Google Drive link">
                </div>
            </div>
        </div>

        <div>
            <label class="splis-label" for="implementing_bodies">Implementing bodies / departments</label>
            <textarea name="implementing_bodies" id="implementing_bodies" rows="2" class="splis-input">{{ old('implementing_bodies', $ordinance->implementing_bodies) }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="mandate_ppa">Mandate / PPA</label>
                <input type="text" name="mandate_ppa" id="mandate_ppa" value="{{ old('mandate_ppa', $ordinance->mandate_ppa) }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="remarks">Remarks</label>
                <input type="text" name="remarks" id="remarks" value="{{ old('remarks', $ordinance->remarks) }}" class="splis-input">
            </div>
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save</button>
            @if ($isEdit)
                <a href="{{ route('ordinances.show', $ordinance) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                    <x-icon name="eye" class="h-4 w-4" />
                    View record
                </a>
            @endif
            <a href="{{ route('ordinances.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
