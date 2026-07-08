@php
    /** @var string $name */
    /** @var string $label */
    /** @var list<int> $selectedIds */
    /** @var \Illuminate\Support\Collection<int, \App\Models\BoardMember> $boardMembers */

    $options = $boardMembers->map(function ($member) {
        $assignment = $member->termAssignments->first();
        $label = $member->displayName();
        if ($assignment?->district) {
            $label .= ' — '.$assignment->district;
        }

        return ['id' => $member->id, 'label' => $label];
    })->values();
@endphp

<div>
    @if ($showLabel ?? true)
        <label class="splis-label">{{ $label }}</label>
    @endif
    <div
        class="splis-member-multi"
        data-member-multi
        data-field="{{ $name }}"
        data-options='@json($options)'
        data-selected='@json(array_values($selectedIds))'
    >
        <div class="splis-member-multi-control">
            <div class="splis-member-multi-inner">
                <div class="splis-member-multi-chips" data-member-chips aria-live="polite"></div>
                <input
                    type="search"
                    class="splis-member-multi-search"
                    placeholder="Search and add members…"
                    autocomplete="off"
                    data-member-search
                >
            </div>
            <div class="splis-member-multi-panel" data-member-panel>
                <div class="splis-member-multi-list" data-member-list role="listbox" aria-label="{{ $label }}"></div>
            </div>
        </div>
        <div data-member-hidden></div>
    </div>
</div>
