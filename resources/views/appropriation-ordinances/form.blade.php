@extends('layouts.app')

@php
    $isEdit = $appropriationOrdinance->exists;
@endphp

@section('title', ($isEdit ? 'Edit' : 'New').' Appropriation Ordinance — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit appropriation ordinance' : 'New appropriation ordinance' }}</h1>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('appropriation-ordinances.update', $appropriationOrdinance) : route('appropriation-ordinances.store') }}" class="splis-card splis-card-body space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="splis-label" for="ordinance_no">Appro. Ord. No.</label>
                <input type="number" name="ordinance_no" id="ordinance_no" value="{{ old('ordinance_no', $appropriationOrdinance->ordinance_no) }}" min="1" required class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="series_year">Series year</label>
                <input type="number" name="series_year" id="series_year" value="{{ old('series_year', $appropriationOrdinance->series_year) }}" min="1900" max="2100" required class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="date_received">Date received</label>
                <input type="date" name="date_received" id="date_received" value="{{ old('date_received', $appropriationOrdinance->date_received?->format('Y-m-d')) }}" class="splis-input">
            </div>
        </div>

        <div>
            <label class="splis-label" for="subject">Title</label>
            <textarea name="subject" id="subject" rows="4" required class="splis-input">{{ old('subject', $appropriationOrdinance->subject) }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="date_passed">Date passed by the SP</label>
                <input type="date" name="date_passed" id="date_passed" value="{{ old('date_passed', $appropriationOrdinance->date_passed?->format('Y-m-d')) }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="date_approved">Date approved by the Governor</label>
                <input type="date" name="date_approved" id="date_approved" value="{{ old('date_approved', $appropriationOrdinance->date_approved?->format('Y-m-d')) }}" class="splis-input">
            </div>
        </div>

        <div>
            <label class="splis-label" for="pdf_url">PDF URL</label>
            <input type="url" name="pdf_url" id="pdf_url" value="{{ old('pdf_url', $appropriationOrdinance->pdf_url) }}" class="splis-input" placeholder="https://">
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save</button>
            <a href="{{ route('appropriation-ordinances.index') }}" class="splis-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
