@php
    $fieldId = $id ?? $name;
    $currentValue = old($name, $value ?? '');
@endphp

<div class="splis-combobox" data-combobox data-options='@json($options)'>
    <label class="splis-label" for="{{ $fieldId }}">{{ $label }}</label>
    <div class="splis-combobox-control">
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            value="{{ $currentValue }}"
            class="splis-input splis-combobox-input"
            placeholder="{{ $placeholder ?? 'Type or choose…' }}"
            autocomplete="off"
            data-combobox-input
        >
        <button type="button" class="splis-combobox-trigger" data-combobox-trigger aria-label="Show {{ $label }} options">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <div class="splis-combobox-panel" data-combobox-panel>
            <div class="splis-combobox-list" data-combobox-list role="listbox" aria-label="{{ $label }} options"></div>
        </div>
    </div>
</div>
