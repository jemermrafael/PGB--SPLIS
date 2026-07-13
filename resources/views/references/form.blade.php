@extends('layouts.app')

@section('title', ($reference->exists ? 'Edit' : 'Add').' Reference Material — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">{{ $reference->exists ? 'Edit reference material' : 'Add Reference Material' }}</h1>
            <p class="splis-page-subtitle">Upload and maintain official reference documents and metadata.</p>
        </div>
    </div>

    <form
        method="POST"
        action="{{ $reference->exists ? route('references.update', $reference) : route('references.store') }}"
        enctype="multipart/form-data"
        class="splis-card splis-card-body space-y-4"
    >
        @csrf
        @if ($reference->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="splis-label" for="title">Title</label>
                <input type="text" name="title" id="title" class="splis-input" value="{{ old('title', $reference->title) }}" required>
            </div>
            <div>
                <label class="splis-label" for="document_type">Document type</label>
                <select name="document_type" id="document_type" class="splis-select" required>
                    @foreach ($documentTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('document_type', $reference->document_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label" for="status">Status</label>
                <select name="status" id="status" class="splis-select" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $reference->status ?: 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label" for="reference_no">Reference no.</label>
                <input type="text" name="reference_no" id="reference_no" class="splis-input" value="{{ old('reference_no', $reference->reference_no) }}">
            </div>
            <div>
                <label class="splis-label" for="version_no">Version</label>
                <input type="text" name="version_no" id="version_no" class="splis-input" value="{{ old('version_no', $reference->version_no) }}" placeholder="e.g. 1.0">
            </div>
            <div>
                <label class="splis-label" for="issuing_office">Issuing office</label>
                <input type="text" name="issuing_office" id="issuing_office" class="splis-input" value="{{ old('issuing_office', $reference->issuing_office) }}">
            </div>
            <div>
                <label class="splis-label" for="supersedes_reference_material_id">Supersedes</label>
                <select name="supersedes_reference_material_id" id="supersedes_reference_material_id" class="splis-select">
                    <option value="">None</option>
                    @foreach ($supersedesOptions as $option)
                        <option value="{{ $option->id }}" @selected((string) old('supersedes_reference_material_id', $reference->supersedes_reference_material_id) === (string) $option->id)>
                            {{ $option->title }}{{ $option->reference_no ? ' ('.$option->reference_no.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label" for="date_issued">Date issued</label>
                <input type="date" name="date_issued" id="date_issued" class="splis-input" value="{{ old('date_issued', $reference->date_issued?->format('Y-m-d')) }}">
            </div>
            <div>
                <label class="splis-label" for="effective_date">Effective date</label>
                <input type="date" name="effective_date" id="effective_date" class="splis-input" value="{{ old('effective_date', $reference->effective_date?->format('Y-m-d')) }}">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label" for="keywords">Keywords</label>
                <input type="text" name="keywords" id="keywords" class="splis-input" value="{{ old('keywords', $reference->keywords) }}" placeholder="comma-separated keywords">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label" for="summary">Summary</label>
                <textarea name="summary" id="summary" rows="4" class="splis-textarea">{{ old('summary', $reference->summary) }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="splis-label" for="file">Document file</label>
                <input type="file" name="file" id="file" class="splis-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                @if ($reference->exists && $reference->hasFile())
                    <p class="mt-1 text-xs text-slate-500">Current file: {{ $reference->original_filename ?: basename($reference->file_path) }}</p>
                @endif
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="splis-btn-primary">{{ $reference->exists ? 'Save changes' : 'Create Reference' }}</button>
            @if ($reference->exists)
                <a href="{{ route('references.show', $reference) }}" class="splis-btn-secondary">Cancel</a>
            @else
                <a href="{{ route('references.index') }}" class="splis-btn-secondary">Cancel</a>
            @endif
        </div>
    </form>
</div>
@endsection

