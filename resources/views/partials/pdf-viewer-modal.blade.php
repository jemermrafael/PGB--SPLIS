@php
    // Shared document viewer modal — opened by any [data-pdf-modal-open] trigger.
@endphp
<div id="splis-pdf-modal" class="splis-modal" hidden>
    <div class="splis-modal-backdrop" data-pdf-modal-close tabindex="-1" aria-hidden="true"></div>
    <div class="splis-modal-panel !max-h-[92vh] !max-w-5xl" data-pdf-modal-panel role="dialog" aria-modal="true" aria-labelledby="splis-pdf-modal-title">
        <div class="splis-modal-header">
            <h3 id="splis-pdf-modal-title" class="splis-modal-title">Document</h3>
            <div class="flex items-center gap-2">
                <a
                    id="splis-pdf-modal-open-tab"
                    href="#"
                    target="_blank"
                    rel="noopener"
                    class="splis-btn-ghost inline-flex items-center gap-1.5 !px-2 !py-1 text-xs"
                >
                    <x-icon name="external-link" class="h-3.5 w-3.5" />
                    Open in new tab
                </a>
                <button
                    type="button"
                    id="splis-pdf-modal-fullscreen"
                    class="splis-btn-ghost inline-flex items-center gap-1.5 !px-2 !py-1 text-xs"
                    aria-pressed="false"
                >
                    <x-icon name="maximize" class="h-3.5 w-3.5" data-fullscreen-icon="enter" />
                    <x-icon name="minimize" class="hidden h-3.5 w-3.5" data-fullscreen-icon="exit" />
                    <span data-fullscreen-label>Fullscreen</span>
                </button>
                <button type="button" class="splis-modal-close" data-pdf-modal-close aria-label="Close">×</button>
            </div>
        </div>
        <div class="splis-modal-body !p-0">
            <iframe
                id="splis-pdf-modal-frame"
                title="Document preview"
                class="splis-pdf-modal-frame block h-[75vh] w-full border-0 bg-slate-100 dark:bg-slate-900"
                src="about:blank"
            ></iframe>
            <img
                id="splis-pdf-modal-image"
                alt=""
                class="hidden max-h-[75vh] w-full bg-slate-100 object-contain dark:bg-slate-900"
            >
        </div>
    </div>
</div>
