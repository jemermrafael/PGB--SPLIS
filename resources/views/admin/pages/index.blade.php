@extends('layouts.app')

@section('title', 'Page backgrounds — '.config('app.name'))

@section('content')
<div class="max-w-3xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Pages</h1>
            <p class="splis-page-subtitle">Set classic background color and image options per page (Elementor-style).</p>
        </div>
    </div>

    <div class="space-y-4">
        @php
            $catalogGroups = collect($pageCatalog)->groupBy(fn (array $meta) => $meta['group'] ?? 'pages', true);
        @endphp
        @foreach ($catalogGroups as $group => $groupPages)
            @if ($group === 'committees')
                <div class="pt-4">
                    <p class="splis-admin-section-title">Committee items</p>
                    <p class="mb-3 text-sm text-slate-500">Each committee page can have its own background.</p>
                </div>
            @endif

            @foreach ($groupPages as $pageKey => $meta)
            @php
                $bg = $backgrounds->get($pageKey);
                $useOld = session('editing_page') === $pageKey && $errors->any();
                $storedType = ($bg?->background_type === 'classic' && (filled($bg?->color) || $bg?->hasImage()))
                    ? 'classic'
                    : 'none';
                $type = $useOld ? old('background_type', $storedType) : $storedType;
                $hasImage = $bg?->hasImage() === true;
            @endphp

            <details class="splis-card overflow-hidden" @if (session('editing_page') === $pageKey) open @endif>
                <summary class="cursor-pointer list-none px-5 py-4 font-medium text-slate-900 dark:text-slate-100 [&::-webkit-details-marker]:hidden">
                    <div class="flex items-center justify-between gap-3">
                        <span>{{ $meta['label'] }}</span>
                        <span class="text-xs font-normal text-slate-500">
                            @if (($bg?->background_type ?? 'none') === 'classic' && (filled($bg?->color) || $hasImage))
                                Classic
                            @else
                                Default
                            @endif
                        </span>
                    </div>
                </summary>

                <form
                    method="POST"
                    action="{{ route('admin.pages.update', $pageKey) }}"
                    enctype="multipart/form-data"
                    class="space-y-4 border-t border-slate-200 px-5 py-4 dark:border-slate-700"
                    data-page-bg-form
                >
                    @csrf
                    @method('PUT')

                    <div>
                        <p class="splis-label mb-2">Background Type</p>
                        <div class="inline-flex overflow-hidden rounded-lg border border-slate-200 dark:border-slate-600">
                            <label class="cursor-pointer">
                                <input type="radio" name="background_type" value="none" class="peer sr-only" @checked(old('background_type', $type) === 'none') data-bg-type>
                                <span class="block px-3 py-2 text-sm peer-checked:bg-slate-200 peer-checked:font-semibold dark:peer-checked:bg-slate-700">None</span>
                            </label>
                            <label class="cursor-pointer border-l border-slate-200 dark:border-slate-600">
                                <input type="radio" name="background_type" value="classic" class="peer sr-only" @checked(old('background_type', $type) === 'classic') data-bg-type>
                                <span class="block px-3 py-2 text-sm peer-checked:bg-slate-200 peer-checked:font-semibold dark:peer-checked:bg-slate-700">Classic</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-4 {{ $type === 'classic' ? '' : 'hidden' }}" data-classic-fields>
                        <div>
                            <label class="splis-label" for="color-{{ $pageKey }}">Color</label>
                            <div class="mt-1 flex items-center gap-3">
                                @php
                                    $colorValue = $useOld ? old('color', $bg?->color) : $bg?->color;
                                    $pickerValue = filled($colorValue) && str_starts_with((string) $colorValue, '#')
                                        ? $colorValue
                                        : '#f8fafc';
                                    $positionValue = $useOld ? old('position', $bg?->position ?? 'default') : ($bg?->position ?? 'default');
                                    $attachmentValue = $useOld ? old('attachment', $bg?->attachment ?? 'default') : ($bg?->attachment ?? 'default');
                                    $repeatValue = $useOld ? old('repeat', $bg?->repeat ?? 'default') : ($bg?->repeat ?? 'default');
                                    $sizeValue = $useOld ? old('size', $bg?->size ?? 'default') : ($bg?->size ?? 'default');
                                @endphp
                                <input
                                    type="color"
                                    name="color_picker"
                                    value="{{ $pickerValue }}"
                                    class="h-10 w-12 cursor-pointer rounded border border-slate-200 bg-white p-1 dark:border-slate-600"
                                    data-color-picker
                                >
                                <input
                                    type="text"
                                    name="color"
                                    id="color-{{ $pageKey }}"
                                    value="{{ $colorValue }}"
                                    class="splis-input max-w-xs"
                                    placeholder="#f8fafc or leave blank"
                                    data-color-text
                                >
                            </div>
                        </div>

                        <div>
                            <label class="splis-label" for="image-{{ $pageKey }}">Image</label>
                            @if ($hasImage)
                                <div class="mt-2 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-600">
                                    <img src="{{ $bg->imageUrl() }}" alt="" class="max-h-40 w-full object-cover">
                                </div>
                                <label class="mt-2 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    Remove current image
                                </label>
                            @endif
                            <input
                                type="file"
                                name="image"
                                id="image-{{ $pageKey }}"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                class="splis-input mt-2 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700"
                            >
                            <p class="mt-1 text-xs text-slate-500">JPEG, PNG, WebP, or GIF. Max 5 MB.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="splis-label" for="position-{{ $pageKey }}">Position</label>
                                <select name="position" id="position-{{ $pageKey }}" class="splis-select">
                                    @foreach ($positions as $value => $label)
                                        <option value="{{ $value }}" @selected($positionValue === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="splis-label" for="attachment-{{ $pageKey }}">Attachment</label>
                                <select name="attachment" id="attachment-{{ $pageKey }}" class="splis-select">
                                    @foreach ($attachments as $value => $label)
                                        <option value="{{ $value }}" @selected($attachmentValue === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="splis-label" for="repeat-{{ $pageKey }}">Repeat</label>
                                <select name="repeat" id="repeat-{{ $pageKey }}" class="splis-select">
                                    @foreach ($repeats as $value => $label)
                                        <option value="{{ $value }}" @selected($repeatValue === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="splis-label" for="size-{{ $pageKey }}">Display Size</label>
                                <select name="size" id="size-{{ $pageKey }}" class="splis-select">
                                    @foreach ($sizes as $value => $label)
                                        <option value="{{ $value }}" @selected($sizeValue === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="pt-1">
                        <button type="submit" class="splis-btn-primary">Save</button>
                    </div>
                </form>
            </details>
            @endforeach
        @endforeach
    </div>
</div>
@push('scripts')
<script>
    (() => {
        document.querySelectorAll('[data-page-bg-form]').forEach((form) => {
            const classic = form.querySelector('[data-classic-fields]');
            const syncType = () => {
                const selected = form.querySelector('[data-bg-type]:checked');
                if (!classic || !selected) return;
                classic.classList.toggle('hidden', selected.value !== 'classic');
            };
            form.querySelectorAll('[data-bg-type]').forEach((input) => {
                input.addEventListener('change', syncType);
            });
            syncType();

            const picker = form.querySelector('[data-color-picker]');
            const text = form.querySelector('[data-color-text]');
            if (picker && text) {
                picker.addEventListener('input', () => {
                    text.value = picker.value;
                });
                text.addEventListener('change', () => {
                    if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(text.value.trim())) {
                        picker.value = text.value.trim();
                    }
                });
            }
        });
    })();
</script>
@endpush
@endsection
