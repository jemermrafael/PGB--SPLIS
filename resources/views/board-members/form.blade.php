@extends('layouts.app')

@php
    $isEdit = $boardMember->exists;
    $districts = config('board_members.districts', []);
    $selectedDistrict = old('district', $assignment?->district ?? $boardMember->district);
    $isActive = old('is_active', $assignment?->is_active ?? $boardMember->is_active);
@endphp

@section('title', ($isEdit ? 'Edit Board Member' : 'New Board Member').' — '.config('app.name'))

@section('content')
<div class="max-w-2xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit Board Member' : 'New Board Member' }}</h1>
            <p class="splis-page-subtitle">Personnel record and term roster assignment.</p>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('board-members.update', $boardMember) : route('board-members.store') }}" class="splis-card splis-card-body space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="splis-label" for="honorific">Honorific</label>
                <input type="text" name="honorific" id="honorific" value="{{ old('honorific', $boardMember->honorific) }}" placeholder="Hon." class="splis-input">
            </div>
            <div class="md:col-span-2">
                <label class="splis-label" for="name">Full name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $boardMember->name) }}" required class="splis-input">
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-800/50">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Term roster</h2>

            <div class="mb-4">
                <label class="splis-label" for="committee_term_id">Election term</label>
                <select name="committee_term_id" id="committee_term_id" required class="splis-input">
                    @foreach ($terms as $option)
                        <option value="{{ $option->id }}" @selected(old('committee_term_id', $term->id) == $option->id)>
                            {{ $option->label }}@if ($option->is_current) (current)@endif
                        </option>
                    @endforeach
                </select>
                @if ($isEdit)
                    <p class="mt-1 text-xs text-slate-500">District and status apply to the selected term only.</p>
                @endif
            </div>

            <div>
                <label class="splis-label" for="district">District</label>
                <select name="district" id="district" class="splis-input">
                    <option value="">— Select district —</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district }}" @selected($selectedDistrict === $district)>{{ $district }}</option>
                    @endforeach
                    @if ($selectedDistrict && ! in_array($selectedDistrict, $districts, true))
                        <option value="{{ $selectedDistrict }}" selected>{{ $selectedDistrict }}</option>
                    @endif
                </select>
            </div>

            <div class="mt-4">
                <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked($isActive) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Active on this term’s roster (available for committee assignment)
                </label>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save</button>
            @if ($isEdit)
                <a href="{{ route('board-members.show', ['boardMember' => $boardMember, 'term' => $term->id]) }}" class="splis-btn-secondary">View Profile</a>
            @endif
            <a href="{{ route('board-members.index', ['term' => $term->id]) }}" class="splis-btn-secondary">Cancel</a>
        </div>
    </form>

    @if ($isEdit)
        @can('delete', $boardMember)
            <form
                method="POST"
                action="{{ route('board-members.destroy', $boardMember) }}"
                class="mt-6"
                data-confirm-submit
                data-confirm-title="Move Board Member to trash?"
                data-confirm-message="Move {{ $boardMember->displayName() }} to trash? Superadmin can restore from Trash."
                data-confirm-label="Move to trash"
            >
                @csrf
                @method('DELETE')
                <input type="hidden" name="term" value="{{ $term->id }}">
                <button type="submit" class="splis-btn-danger">Move to trash</button>
            </form>
        @endcan
    @endif
</div>
@endsection
