@extends('layouts.app')

@php
    $isEdit = $committee->exists;
@endphp

@section('title', ($isEdit ? 'Edit Committee' : 'New Committee').' — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit committee' : 'New committee' }}</h1>
            <p class="splis-page-subtitle">{{ $isEdit ? 'Update committee details.' : 'Add a standing committee to the master list.' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('committees.update', $committee) : route('committees.store') }}" class="splis-card splis-card-body space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
                <label class="splis-label" for="sort_order">List no.</label>
                <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $committee->sort_order) }}" min="0" required class="splis-input">
            </div>
            <div class="md:col-span-3">
                <label class="splis-label" for="name">Committee name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $committee->name) }}" required class="splis-input">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="chair">Chair</label>
                <input type="text" name="chair" id="chair" value="{{ old('chair', $committee->chair) }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="vice_chair">Vice chair</label>
                <input type="text" name="vice_chair" id="vice_chair" value="{{ old('vice_chair', $committee->vice_chair) }}" class="splis-input">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="splis-label" for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $committee->email) }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="secretary">Committee secretary</label>
                <input type="text" name="secretary" id="secretary" value="{{ old('secretary', $committee->secretary) }}" class="splis-input">
            </div>
        </div>

        <div>
            <label class="splis-label" for="members">Members</label>
            <textarea name="members" id="members" rows="5" class="splis-textarea">{{ old('members', $committee->members) }}</textarea>
        </div>

        <div>
            <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $committee->is_active)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Active (shown in dropdowns)
            </label>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save committee</button>
            <a href="{{ route('committees.index') }}" class="splis-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
