@php
    /** @var string $name */
    /** @var string $htmlName */
    /** @var string $plain */
    /** @var string|null $html */
    /** @var string $label */
    /** @var string $editorId */
    /** @var string|null $hint */
    /** @var string|null $editorClass */
@endphp
<div class="space-y-2" data-scr-rich-wrap>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="splis-label !mb-0" for="{{ $editorId }}">{{ $label }}</label>
        <div class="splis-ob-rich-title-toolbar !border-0 !bg-transparent !p-0" role="toolbar" aria-label="{{ $label }} formatting">
            <button
                type="button"
                class="splis-ob-rich-title-button"
                data-scr-rich-command="bold"
                title="Bold (Ctrl+B)"
                aria-label="Bold"
            ><strong>B</strong></button>
            <button
                type="button"
                class="splis-ob-rich-title-button"
                data-scr-rich-command="underline"
                title="Underline (Ctrl+U)"
                aria-label="Underline"
            ><span class="underline">U</span></button>
            <button
                type="button"
                class="splis-ob-rich-title-button splis-ob-rich-title-button--highlight"
                data-scr-rich-command="highlight"
                title="Highlight (Ctrl+H)"
                aria-label="Highlight"
            ><strong>H</strong></button>
        </div>
    </div>
    <div class="splis-ob-rich-title-wrap">
        <div
            id="{{ $editorId }}"
            class="splis-ob-rich-title {{ $editorClass ?? '' }}"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            data-scr-rich-editor
        ></div>
    </div>
    <input type="hidden" name="{{ $name }}" value="{{ $plain }}" data-scr-rich-plain>
    <input type="hidden" name="{{ $htmlName }}" value="{{ $html ?? '' }}" data-scr-rich-html>
    <p class="text-xs text-slate-500">{{ $hint ?? '' }}</p>
</div>
