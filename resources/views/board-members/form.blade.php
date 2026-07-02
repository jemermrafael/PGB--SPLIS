@extends('layouts.app')

@php
    $isEdit = $boardMember->exists;
    $districts = config('board_members.districts', []);
    $selectedDistrict = old('district', $boardMember->district);
@endphp

@section('title', ($isEdit ? 'Edit Board Member' : 'New Board Member').' — '.config('app.name'))

@section('content')
<div class="max-w-2xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit board member' : 'New board member' }}</h1>
            <p class="splis-page-subtitle">Personnel record for committee assignments.</p>
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

        <div>
            <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $boardMember->is_active)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Active (available for committee assignment)
            </label>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save</button>
            @if ($isEdit)
                <a href="{{ route('board-members.show', $boardMember) }}" class="splis-btn-secondary">View profile</a>
            @endif
            <a href="{{ route('board-members.index') }}" class="splis-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
