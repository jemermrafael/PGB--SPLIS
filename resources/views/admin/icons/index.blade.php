@extends('layouts.app')

@section('title', 'Icon library — '.config('app.name'))

@section('content')
<div class="splis-page-header">
    <div>
        <h1 class="splis-page-title">Icon Library</h1>
        <p class="splis-page-subtitle">Built-in presets and uploaded icons you can reuse for committees.</p>
    </div>
</div>

<p class="splis-admin-section-title">Upload</p>
<div class="mb-8 splis-card p-6">
    <h2 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Add icons</h2>
    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Select one or more SVG/PNG files (max 512 KB each). Names are taken from the filenames.</p>
    <form method="POST" action="{{ route('admin.icons.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4 sm:flex-row sm:items-end">
        @csrf
        <div class="min-w-0 flex-1">
            <label for="icons" class="splis-label">Icon files</label>
            <input
                type="file"
                name="icons[]"
                id="icons"
                accept=".png,.svg,image/png,image/svg+xml"
                multiple
                required
                class="splis-input mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700"
            >
            @error('icons')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('icons.*')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="splis-btn-primary inline-flex items-center justify-center gap-2">
            <x-icon name="plus" class="h-4 w-4" />
            Upload
        </button>
    </form>
</div>

<p class="splis-admin-section-title">Uploaded icons</p>
<div class="mb-10 splis-card p-6">
    @if ($items->isEmpty())
        <p class="text-sm text-slate-500">No uploaded icons yet. Upload one above to reuse it later.</p>
    @else
        <ul class="admin-icon-library-grid">
            @foreach ($items as $item)
                <li class="flex flex-col items-center gap-1.5 rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900/40">
                    <div class="flex h-10 w-10 items-center justify-center rounded-md border border-slate-100 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800">
                        @if ($item->existsLocally())
                            <img
                                src="{{ $item->publicUrl() }}"
                                alt="{{ $item->name }}"
                                class="max-h-full max-w-full object-contain"
                            >
                        @else
                            <span class="text-[10px] text-slate-400">Missing</span>
                        @endif
                    </div>
                    <div class="w-full text-center">
                        <p class="truncate text-[10px] font-medium text-slate-800 dark:text-slate-200" title="{{ $item->name }}">{{ $item->name }}</p>
                        <p class="mt-0.5 text-[9px] text-slate-500">
                            @if ($item->committees_count > 0)
                                {{ $item->committees_count }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <form
                        method="POST"
                        action="{{ route('admin.icons.destroy', $item) }}"
                        data-confirm-submit
                        data-confirm-title="Remove this icon?"
                        data-confirm-message="Committees using it will fall back to their preset or auto icon."
                        data-confirm-label="Remove"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="splis-btn-ghost inline-flex items-center gap-1 !px-1.5 !py-0.5 text-[10px] text-red-600 dark:text-red-400">
                            <x-icon name="trash" class="h-3 w-3" />
                            Remove
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<p class="splis-admin-section-title">Built-in presets</p>
<div class="splis-card p-6">
    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">These SVG presets ship with the app and are always available in the committee icon chooser.</p>
    <ul class="admin-icon-library-grid">
        @foreach ($presetPaths as $key => $path)
            <li class="flex flex-col items-center gap-1.5 rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900/40">
                <span class="flex h-10 w-10 items-center justify-center rounded-md border border-slate-100 bg-slate-50 text-brand-800 dark:border-slate-700 dark:bg-slate-800 dark:text-brand-200">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                    </svg>
                </span>
                <span class="text-center text-[10px] capitalize leading-tight text-slate-600 dark:text-slate-300">{{ str_replace('-', ' ', $key) }}</span>
            </li>
        @endforeach
    </ul>
</div>
@endsection
