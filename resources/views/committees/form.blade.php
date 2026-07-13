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
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit committee' : 'New committee' }}</h1>
            <p class="splis-page-subtitle">Assign chair, vice chair, and members from the board roster for a specific term.</p>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('board-members.index') }}" class="splis-btn-secondary">Board Members</a>
        <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary">Election terms</a>
    </div>

    <form method="POST" action="{{ $isEdit ? route('committees.update', $committee) : route('committees.store') }}" class="splis-card splis-card-body space-y-6">
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
                        <option value="">— Select board member —</option>
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
            <button type="submit" class="splis-btn-primary">Save committee</button>
            @if ($isEdit)
                <a href="{{ route('committees.show', ['committee' => $committee, 'term' => $term->id]) }}" class="splis-btn-secondary">View roster</a>
            @endif
            <a href="{{ route('committees.index') }}" class="splis-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
