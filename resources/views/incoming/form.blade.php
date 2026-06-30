@extends('layouts.app')

@section('title', (isset($incoming) && $incoming->exists ? 'Edit Incoming' : 'New Incoming').' — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ isset($incoming) && $incoming->exists ? 'Edit Incoming' : 'New Incoming' }}</h1>
            <p class="splis-page-subtitle">{{ isset($incoming) && $incoming->exists ? 'Update workflow details for this item.' : 'Record a new incoming document manually.' }}</p>
        </div>
        @if (isset($incoming) && $incoming->exists)
            @can('publish', $incoming)
                <a href="{{ route('incoming.publish', $incoming) }}" class="splis-btn-primary">Publish to Resolution</a>
            @endcan
        @endif
    </div>

    <form method="POST" action="{{ isset($incoming) && $incoming->exists ? route('incoming.update', $incoming) : route('incoming.store') }}" class="splis-card splis-card-body space-y-5">
        @csrf
        @if (isset($incoming) && $incoming->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="mun_resolution_no">Municipal Resolution No.</label>
                <input type="text" name="mun_resolution_no" id="mun_resolution_no" value="{{ old('mun_resolution_no', $incoming->mun_resolution_no ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="date_received">Date Received</label>
                <input type="date" name="date_received" id="date_received" value="{{ old('date_received', isset($incoming) && $incoming->date_received ? $incoming->date_received->format('Y-m-d') : '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="mun_series">Municipal Series</label>
                <input type="text" name="mun_series" id="mun_series" value="{{ old('mun_series', $incoming->mun_series ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="municipality">Municipality</label>
                <input type="text" name="municipality" id="municipality" value="{{ old('municipality', $incoming->municipality ?? '') }}" class="splis-input">
            </div>
        </div>

        <div>
            <label class="splis-label" for="title">Title</label>
            <textarea name="title" id="title" rows="6" class="splis-textarea">{{ old('title', $incoming->title ?? '') }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="action_taken">Action Taken</label>
                <select name="action_taken" id="action_taken" class="splis-select">
                    <option value="">—</option>
                    @foreach ($actionTakenOptions as $option)
                        <option value="{{ $option }}" @selected(old('action_taken', $incoming->action_taken ?? '') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            @include('partials.combobox-field', [
                'name' => 'referral',
                'id' => 'referral',
                'label' => 'Referral',
                'value' => $incoming->referral ?? '',
                'options' => $referralOptions,
                'placeholder' => 'Search committees…',
            ])

            <div>
                <label class="splis-label" for="agenda">Agenda</label>
                <input type="text" name="agenda" id="agenda" value="{{ old('agenda', $incoming->agenda ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="workflow_status">Status</label>
                <input type="text" name="workflow_status" id="workflow_status" value="{{ old('workflow_status', $incoming->workflow_status ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="sp_res_no">SP Resolution No.</label>
                <input type="text" name="sp_res_no" id="sp_res_no" value="{{ old('sp_res_no', $incoming->sp_res_no ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="sp_series">SP Series (Year)</label>
                <input type="number" name="sp_series" id="sp_series" value="{{ old('sp_series', $incoming->sp_series ?? '') }}" min="1900" max="2100" class="splis-input">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label" for="sp_title">SP Title</label>
                <textarea name="sp_title" id="sp_title" rows="6" class="splis-textarea">{{ old('sp_title', $incoming->sp_title ?? '') }}</textarea>
            </div>
            <div>
                <label class="splis-label" for="sp_date_approved">SP Date Approved</label>
                <input type="date" name="sp_date_approved" id="sp_date_approved" value="{{ old('sp_date_approved', isset($incoming) && $incoming->sp_date_approved ? $incoming->sp_date_approved->format('Y-m-d') : '') }}" class="splis-input">
            </div>

            @include('partials.combobox-field', [
                'name' => 'concerned_agency',
                'id' => 'concerned_agency',
                'label' => 'Concerned Agency',
                'value' => $incoming->concerned_agency ?? '',
                'options' => $concernedAgencyOptions,
                'placeholder' => 'Search agency…',
            ])

            @include('partials.keyword-tags-field', [
                'name' => 'keyword',
                'id' => 'keyword',
                'label' => 'Keywords',
                'value' => $incoming->keyword ?? '',
                'keywordsUrl' => route('incoming.keywords'),
                'placeholder' => 'Add keyword…',
            ])

            <div class="md:col-span-2">
                <label class="splis-label" for="remarks">Remarks</label>
                <textarea name="remarks" id="remarks" rows="2" class="splis-textarea">{{ old('remarks', $incoming->remarks ?? '') }}</textarea>
            </div>
            <div>
                <label class="splis-label" for="mun_pdf_url">Municipal PDF URL</label>
                <input type="url" name="mun_pdf_url" id="mun_pdf_url" value="{{ old('mun_pdf_url', $incoming->mun_pdf_url ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="sp_pdf_url">SP PDF URL</label>
                <input type="url" name="sp_pdf_url" id="sp_pdf_url" value="{{ old('sp_pdf_url', $incoming->sp_pdf_url ?? '') }}" class="splis-input">
            </div>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save</button>
            <a href="{{ isset($incoming) && $incoming->exists ? route('incoming.show', $incoming) : route('incoming.index') }}" class="splis-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
