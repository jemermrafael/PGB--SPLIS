@extends('layouts.app')

@section('title', ($resolution->resolution_no ?? 'New Resolution').' — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ isset($resolution) ? 'Edit Resolution' : 'New Resolution' }}</h1>
            <p class="splis-page-subtitle">{{ isset($resolution) ? 'Update resolution details and attachments.' : 'Enter details for a new legislative record.' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ isset($resolution) ? route('resolutions.update', $resolution) : route('resolutions.store') }}" enctype="multipart/form-data" class="splis-card splis-card-body space-y-5">
        @csrf
        @isset($resolution)
            @method('PUT')
        @endisset

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label">Resolution No. *</label>
                <input type="text" name="resolution_no" value="{{ old('resolution_no', $resolution->resolution_no ?? '') }}" required class="splis-input">
            </div>
            <div>
                <label class="splis-label">Series (Year) *</label>
                <input type="number" name="series" value="{{ old('series', $resolution->series ?? date('Y')) }}" required min="1900" max="2100" class="splis-input">
            </div>
        </div>

        <div>
            <label class="splis-label">Title *</label>
            <textarea name="resolution_title" rows="3" required class="splis-textarea">{{ old('resolution_title', $resolution->resolution_title ?? '') }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label">Category</label>
                <select name="category_id" class="splis-select">
                    <option value="">—</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id', $resolution->category_id ?? '') == $cat->id)>{{ $cat->description }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Department</label>
                <select name="department_id" class="splis-select">
                    <option value="">—</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(old('department_id', $resolution->department_id ?? '') == $dept->id)>{{ $dept->description }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Municipality</label>
                <select name="municipality_id" class="splis-select">
                    <option value="">—</option>
                    @foreach ($municipalities as $mun)
                        <option value="{{ $mun->id }}" @selected(old('municipality_id', $resolution->municipality_id ?? '') == $mun->id)>{{ $mun->description }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Date Approved</label>
                <input type="date" name="date_approved" value="{{ old('date_approved', isset($resolution) && $resolution->date_approved ? $resolution->date_approved->format('Y-m-d') : '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label">Sponsored By</label>
                <input type="text" name="sponsored_by" value="{{ old('sponsored_by', $resolution->sponsored_by ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label">Status</label>
                <select name="status" class="splis-select">
                    @foreach (['draft', 'approved', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $resolution->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Keyword</label>
                <input type="text" name="keyword" value="{{ old('keyword', $resolution->keyword ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label">Committee</label>
                <input type="text" name="committee" value="{{ old('committee', $resolution->committee ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label">App/Ord No.</label>
                <input type="text" name="app_ord_no" value="{{ old('app_ord_no', $resolution->app_ord_no ?? '') }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label">Amount</label>
                <input type="number" name="amount" value="{{ old('amount', $resolution->amount ?? '') }}" min="0" class="splis-input">
            </div>
        </div>

        <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <input type="hidden" name="province" value="0">
            <input type="checkbox" name="province" value="1" @checked(old('province', $resolution->province ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Province-wide resolution
        </label>

        <div>
            <label class="splis-label">PDF Document</label>
            <input type="file" name="pdf" accept="application/pdf" class="splis-input !py-2 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
            <p class="mt-1.5 text-xs text-slate-400">Saved as {series}/{resolution_no}.pdf in storage</p>
        </div>

        <div class="flex gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="splis-btn-primary">Save resolution</button>
            <a href="{{ route('resolutions.index') }}" class="splis-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
