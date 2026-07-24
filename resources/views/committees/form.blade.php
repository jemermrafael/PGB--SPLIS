@extends('layouts.app')

@php
    $isEdit = $committee->exists;
    $selectedMemberIds = old('member_ids', $roster['member_ids'] ?? []);
    $selectedIconKey = old('icon_key', $committee->icon_key);
    $selectedIconKey = $selectedIconKey === null ? '' : (string) $selectedIconKey;
    $selectedLibraryId = old('icon_library_id', $committee->icon_library_id);
    $selectedLibraryId = $selectedLibraryId === null || $selectedLibraryId === '' ? '' : (string) $selectedLibraryId;
    $hasCustomIcon = \App\Support\CommitteeIcon::hasCustomFile($committee);
    $hasDirectUpload = filled($committee->icon_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($committee->icon_path);
    $iconKeys = $iconKeys ?? \App\Support\CommitteeIcon::allowedKeys();
    $iconPaths = $iconPaths ?? \App\Support\CommitteeIcon::paths();
    $libraryIcons = $libraryIcons ?? collect();
    $canManageIcon = auth()->user()?->isSuperadmin() === true;
@endphp

@section('title', ($isEdit ? 'Edit Committee' : 'New Committee').' — '.config('app.name'))

@section('content')
<div class="{{ $canManageIcon ? 'max-w-6xl' : 'max-w-3xl' }}">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit Committee' : 'New committee' }}</h1>
            <p class="splis-page-subtitle">Assign Chair, Vice Chair, and Members from the Board roster for a specific term.</p>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('board-members.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="users" class="h-4 w-4" />
            Board Members
        </a>
        <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="calendar" class="h-4 w-4" />
            Election Terms
        </a>
    </div>

    <form method="POST" action="{{ $isEdit ? route('committees.update', $committee) : route('committees.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div @class([
            'grid gap-6',
            'lg:grid-cols-[minmax(0,1fr)_22rem]' => $canManageIcon,
        ])>
            <div class="splis-card splis-card-body space-y-6">
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

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="splis-btn-primary">Save Committee</button>
                    @if ($isEdit)
                        <a href="{{ route('committees.show', ['committee' => $committee, 'term' => $term->id]) }}" class="splis-btn-secondary">View Roster</a>
                    @endif
                    <a href="{{ route('committees.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                        Cancel
                    </a>
                </div>
            </div>

            @if ($canManageIcon)
                <aside class="splis-card splis-card-body h-fit space-y-4 lg:sticky lg:top-24">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Icon</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Icon pack ({{ count($iconKeys) }} presets)
                            @if ($libraryIcons->isNotEmpty())
                                · {{ $libraryIcons->count() }} library
                            @endif
                            . Custom upload or library icon overrides the preset.
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-3">
                        <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg border px-2 py-3 text-center text-xs transition @if ($selectedIconKey === '' && $selectedLibraryId === '' && ! $hasDirectUpload) border-brand-500 bg-white ring-2 ring-brand-200 dark:bg-slate-900 @else border-slate-200 bg-white hover:border-slate-300 dark:border-slate-600 dark:bg-slate-900/40 @endif">
                            <input type="radio" name="icon_key" value="" class="sr-only" @checked($selectedIconKey === '') onclick="document.querySelectorAll('input[name=icon_library_id]').forEach(el => el.checked = el.value === '')">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800">Auto</span>
                            <span class="leading-tight text-slate-600 dark:text-slate-300">From name</span>
                        </label>
                        @foreach ($iconKeys as $key)
                            <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg border px-2 py-3 text-center text-xs transition @if ($selectedIconKey === $key && $selectedLibraryId === '') border-brand-500 bg-white ring-2 ring-brand-200 dark:bg-slate-900 @else border-slate-200 bg-white hover:border-slate-300 dark:border-slate-600 dark:bg-slate-900/40 @endif" title="{{ $key }}">
                                <input type="radio" name="icon_key" value="{{ $key }}" class="sr-only" @checked($selectedIconKey === $key && $selectedLibraryId === '') onclick="document.querySelectorAll('input[name=icon_library_id]').forEach(el => el.checked = el.value === '')">
                                <span class="flex h-9 w-9 items-center justify-center text-brand-800 dark:text-brand-200">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPaths[$key] }}" />
                                    </svg>
                                </span>
                                <span class="leading-tight capitalize text-slate-600 dark:text-slate-300">{{ str_replace('-', ' ', $key) }}</span>
                            </label>
                        @endforeach
                    </div>

                    @if ($libraryIcons->isNotEmpty())
                        <div class="border-t border-slate-200 pt-4 dark:border-slate-600">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">From library</p>
                                <a href="{{ route('admin.icons.index') }}" class="text-xs splis-link">Manage</a>
                            </div>
                            <input type="radio" name="icon_library_id" value="" class="sr-only" @checked($selectedLibraryId === '')>
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-3">
                                @foreach ($libraryIcons as $libraryIcon)
                                    @continue(! $libraryIcon->existsLocally())
                                    <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg border px-2 py-3 text-center text-xs transition @if ($selectedLibraryId === (string) $libraryIcon->id) border-brand-500 bg-white ring-2 ring-brand-200 dark:bg-slate-900 @else border-slate-200 bg-white hover:border-slate-300 dark:border-slate-600 dark:bg-slate-900/40 @endif" title="{{ $libraryIcon->name }}">
                                        <input type="radio" name="icon_library_id" value="{{ $libraryIcon->id }}" class="sr-only" @checked($selectedLibraryId === (string) $libraryIcon->id)>
                                        <span class="flex h-9 w-9 items-center justify-center">
                                            <span class="splis-list-committee-icon-glyph" style="--committee-icon: url('{{ $libraryIcon->publicUrl() }}')"></span>
                                        </span>
                                        <span class="leading-tight truncate w-full text-slate-600 dark:text-slate-300">{{ $libraryIcon->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="border-t border-slate-200 pt-4 dark:border-slate-600">
                        <label class="splis-label" for="icon">Upload custom (SVG / PNG)</label>
                        <input type="file" name="icon" id="icon" accept=".png,.svg,image/png,image/svg+xml" class="splis-input">
                        <p class="mt-1 text-xs text-slate-500">Max 512 KB. One-off for this committee. Prefer the Icon Library for reuse.</p>
                        @error('icon')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @if ($hasCustomIcon)
                            <div class="mt-3 flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-600 dark:bg-slate-900">
                                    <span class="splis-list-committee-icon-glyph" style="--committee-icon: url('{{ \App\Support\CommitteeIcon::customUrl($committee) }}')"></span>
                                </div>
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                    <input type="checkbox" name="remove_icon" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    Remove custom / library icon
                                </label>
                            </div>
                        @endif
                    </div>
                </aside>
            @endif
        </div>
    </form>
</div>
@endsection
