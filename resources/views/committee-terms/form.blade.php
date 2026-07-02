@extends('layouts.app')

@php
    $isEdit = $term->exists;
@endphp

@section('title', ($isEdit ? 'Edit Term' : 'New Term').' — '.config('app.name'))

@section('content')
<div class="max-w-2xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit election term' : 'New election term' }}</h1>
            <p class="splis-page-subtitle">Example: 20th Sangguniang Panlalawigan (2025–2028)</p>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('committee-terms.update', $term) : route('committee-terms.store') }}" class="splis-card splis-card-body space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div>
            <label class="splis-label" for="label">Term label</label>
            <input type="text" name="label" id="label" value="{{ old('label', $term->label) }}" required class="splis-input">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="splis-label" for="year_from">Year from</label>
                <input type="number" name="year_from" id="year_from" value="{{ old('year_from', $term->year_from) }}" min="1900" max="2100" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="year_to">Year to</label>
                <input type="number" name="year_to" id="year_to" value="{{ old('year_to', $term->year_to) }}" min="1900" max="2100" class="splis-input">
            </div>
        </div>

        <div>
            <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <input type="hidden" name="is_current" value="0">
                <input type="checkbox" name="is_current" value="1" @checked(old('is_current', $term->is_current)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Current term (used for new committee assignments and OB chair lookup)
            </label>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save term</button>
            <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
