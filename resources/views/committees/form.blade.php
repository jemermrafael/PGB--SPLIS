@extends('layouts.app')

@php
    $isEdit = $committee->exists;
    $selectedMemberIds = old('member_ids', $roster['member_ids'] ?? []);
@endphp

@section('title', ($isEdit ? 'Edit Committee' : 'New Committee').' — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit Committee' : 'New committee' }}</h1>
            <p class="splis-page-subtitle">Assign Chair, Vice Chair, and Members from the Board roster for a specific term.</p>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('board-members.index') }}" class="splis-btn-secondary">Board Members</a>
        <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary">Election Terms</a>
    </div>

    <form method="POST" action="{{ $isEdit ? route('committees.update', $committee) : route('committees.store') }}" enctype="multipart/form-data" class="splis-card splis-card-body space-y-6">
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

        <div>
            <label class="splis-label" for="email">Committee email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $committee->email) }}" class="splis-input">
        </div>

        @php
            $selectedIconKey = old('icon_key', $committee->icon_key);
            $selectedIconKey = $selectedIconKey === null ? '' : (string) $selectedIconKey;
            $hasCustomIcon = \App\Support\CommitteeIcon::hasCustomFile($committee);
            $iconKeys = $iconKeys ?? \App\Support\CommitteeIcon::allowedKeys();
            $iconPaths = $iconPaths ?? \App\Support\CommitteeIcon::paths();
            $canManageIcon = auth()->user()?->isSuperadmin() === true;
        @endphp
        @if ($canManageIcon)
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-800/50">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Icon</h2>
            <p class="mb-4 text-xs text-slate-500">Choose a preset, or upload a custom SVG/PNG. A custom upload overrides the preset. Superadmin only.</p>

            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg border px-2 py-3 text-center text-xs transition @if ($selectedIconKey === '') border-brand-500 bg-white ring-2 ring-brand-200 dark:bg-slate-900 @else border-slate-200 bg-white hover:border-slate-300 dark:border-slate-600 dark:bg-slate-900/40 @endif">
                    <input type="radio" name="icon_key" value="" class="sr-only" @checked($selectedIconKey === '')>
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800">Auto</span>
                    <span class="text-slate-600 dark:text-slate-300">From name</span>
                </label>
                @foreach ($iconKeys as $key)
                    <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg border px-2 py-3 text-center text-xs transition @if ($selectedIconKey === $key) border-brand-500 bg-white ring-2 ring-brand-200 dark:bg-slate-900 @else border-slate-200 bg-white hover:border-slate-300 dark:border-slate-600 dark:bg-slate-900/40 @endif">
                        <input type="radio" name="icon_key" value="{{ $key }}" class="sr-only" @checked($selectedIconKey === $key)>
                        <span class="flex h-8 w-8 items-center justify-center text-brand-800 dark:text-brand-200">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPaths[$key] }}" />
                            </svg>
                        </span>
                        <span class="capitalize text-slate-600 dark:text-slate-300">{{ str_replace('-', ' ', $key) }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="splis-label" for="icon">Upload custom icon (SVG or PNG)</label>
                    <input type="file" name="icon" id="icon" accept=".png,.svg,image/png,image/svg+xml" class="splis-input">
                    <p class="mt-1 text-xs text-slate-500">Max 512 KB. Uploaded icons appear as images in lists.</p>
                    @error('icon')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @if ($hasCustomIcon)
                    <div class="flex items-end gap-3">
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-600 dark:bg-slate-900">
                            <img src="{{ route('committees.icon', $committee) }}" alt="Current custom icon" class="max-h-full max-w-full object-contain">
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="remove_icon" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            Remove custom icon
                        </label>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-800/50">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Board roster</h2>

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
                    <p class="mt-1 text-xs text-slate-500">Saving updates the roster for this term only. Past terms are preserved as history.</p>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="splis-label" for="chair_id">Chair</label>
                    <select name="chair_id" id="chair_id" class="splis-input">
                        <option value="">— Select Board Member —</option>
                        @foreach ($boardMembers as $member)
                            @php $assignment = $member->termAssignments->first(); @endphp
                            <option value="{{ $member->id }}" @selected(old('chair_id', $roster['chair_id'] ?? null) == $member->id)>
                                {{ $member->displayName() }}@if ($assignment?->district) — {{ $assignment->district }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="splis-label" for="vice_chair_id">Vice chair</label>
                    <select name="vice_chair_id" id="vice_chair_id" class="splis-input">
                        <option value="">— Select Board Member —</option>
                        @foreach ($boardMembers as $member)
                            @php $assignment = $member->termAssignments->first(); @endphp
                            <option value="{{ $member->id }}" @selected(old('vice_chair_id', $roster['vice_chair_id'] ?? null) == $member->id)>
                                {{ $member->displayName() }}@if ($assignment?->district) — {{ $assignment->district }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4">
                @include('partials.combobox-field', [
                    'name' => 'secretary',
                    'label' => 'Committee secretary',
                    'id' => 'secretary',
                    'value' => old('secretary', $secretaryName ?? ''),
                    'options' => $secretaryOptions ?? [],
                    'placeholder' => 'Type or choose secretary…',
                ])
                <p class="mt-1 text-xs text-slate-500">Choose from existing committee secretaries or type a new name.</p>
            </div>

            <div class="mt-4">
                <label class="splis-label" for="member_ids">Members</label>
                <select name="member_ids[]" id="member_ids" multiple size="8" class="splis-input min-h-[12rem]">
                    @foreach ($boardMembers as $member)
                        @php $assignment = $member->termAssignments->first(); @endphp
                        <option value="{{ $member->id }}" @selected(in_array($member->id, $selectedMemberIds, true))>
                            {{ $member->displayName() }}@if ($assignment?->district) — {{ $assignment->district }}@endif
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Hold Ctrl (Windows) or Cmd (Mac) to select multiple members.</p>
            </div>
        </div>

        <div>
            <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $committee->is_active)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Active (shown in dropdowns)
            </label>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save Committee</button>
            @if ($isEdit)
                <a href="{{ route('committees.show', ['committee' => $committee, 'term' => $term->id]) }}" class="splis-btn-secondary">View Roster</a>
            @endif
            <a href="{{ route('committees.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
