@php
    $fieldId = $id ?? $name;
    $currentValue = old($name, $value ?? '');
@endphp

<div
    class="splis-keyword-tags md:col-span-2"
    data-keyword-tags
    data-keywords-url="{{ $keywordsUrl }}"
    data-max-length="{{ $maxLength ?? 150 }}"
>
    <label class="splis-label" for="{{ $fieldId }}">{{ $label }}</label>
    <div class="splis-keyword-tags-control">
        <div class="splis-keyword-tags-inner">
            <div class="splis-keyword-tags-chips" data-keyword-chips aria-live="polite"></div>
            <input
                type="text"
                id="{{ $fieldId }}"
                class="splis-keyword-tags-input"
                placeholder="{{ $placeholder ?? 'Type keyword, comma or Enter to add…' }}"
                autocomplete="off"
                data-keyword-input
            >
        </div>
        <button type="button" class="splis-keyword-tags-trigger" data-keyword-trigger aria-label="Show {{ $label }} suggestions">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>
        <input type="hidden" name="{{ $name }}" value="{{ $currentValue }}" data-keyword-hidden>
        <div class="splis-keyword-tags-panel" data-keyword-panel>
            <p class="splis-keyword-tags-panel-title" data-keyword-panel-title>All used keywords</p>
            <div class="splis-keyword-tags-list" data-keyword-list role="listbox" aria-label="{{ $label }} suggestions"></div>
        </div>
    </div>
    <p class="splis-keyword-tags-hint">Separate multiple keywords with a comma. Each keyword can be multiple words.</p>
</div>
