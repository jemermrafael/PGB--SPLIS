@php
    $files = $files ?? collect();
    $grouped = $grouped ?? null;
    $driveUrl = trim((string) ($driveUrl ?? ''));
    $modalId = $modalId ?? 'splis-document-folder-modal';
    $title = $title ?? 'Documents';
    $canManage = $canManage ?? false;
    $agenda = $agenda ?? null;
@endphp

<div id="{{ $modalId }}" class="splis-modal" hidden>
    <div class="splis-modal-backdrop" data-folder-modal-close tabindex="-1" aria-hidden="true"></div>
    <div class="splis-modal-panel !max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title">
        <div class="splis-modal-header">
            <h3 id="{{ $modalId }}-title" class="splis-modal-title">{{ $title }}</h3>
            <button type="button" class="splis-modal-close" data-folder-modal-close aria-label="Close">×</button>
        </div>
        <div class="splis-modal-body space-y-4">
            @if ($grouped instanceof \Illuminate\Support\Collection && $grouped->isNotEmpty())
                <div class="space-y-4">
                    @foreach ($grouped as $folderLabel => $folderFiles)
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ $folderLabel }}
                            </p>
                            <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                @foreach ($folderFiles as $file)
                                    <li class="flex items-center justify-between gap-3 px-3 py-2.5">
                                        <span class="min-w-0 truncate text-sm text-slate-700 dark:text-slate-300" title="{{ $file->original_filename }}">
                                            <span class="mr-1.5 tabular-nums text-slate-400">{{ $loop->iteration }}.</span>
                                            <x-icon name="file-text" class="mr-1.5 inline h-4 w-4 shrink-0 text-slate-400" />
                                            {{ $file->original_filename }}
                                        </span>
                                        <div class="flex shrink-0 items-center gap-2">
                                            @include('partials.pdf-modal-trigger', [
                                                'url' => $file->publicUrl(),
                                                'viewer' => $file->viewerMode(),
                                                'title' => $file->original_filename,
                                                'label' => 'View',
                                                'class' => 'splis-btn-secondary inline-flex items-center gap-2 text-sm',
                                            ])
                                            @if ($canManage && $agenda)
                                                <form
                                                    method="POST"
                                                    action="{{ route('agenda.request-files.destroy', [$agenda, $file]) }}"
                                                    data-confirm-submit
                                                    data-confirm-title="Remove file?"
                                                    data-confirm-message="Remove &quot;{{ $file->original_filename }}&quot; from this request packet?"
                                                    data-confirm-label="Remove"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @elseif ($files->isNotEmpty())
                <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                    @foreach ($files as $file)
                        <li class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <span class="min-w-0 truncate text-sm text-slate-700 dark:text-slate-300" title="{{ $file->original_filename }}">
                                <span class="mr-1.5 tabular-nums text-slate-400">{{ $loop->iteration }}.</span>
                                <x-icon name="file-text" class="mr-1.5 inline h-4 w-4 shrink-0 text-slate-400" />
                                {{ $file->original_filename }}
                            </span>
                            @include('partials.pdf-modal-trigger', [
                                'url' => $file->publicUrl(),
                                'viewer' => $file->viewerMode(),
                                'title' => $file->original_filename,
                                'label' => 'View',
                                'class' => 'splis-btn-secondary inline-flex shrink-0 items-center gap-2 text-sm',
                            ])
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400">No local files in this folder yet.</p>
            @endif

            @if ($driveUrl !== '')
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900/40">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Google Drive folder</p>
                    <a href="{{ $driveUrl }}" target="_blank" rel="noopener" class="splis-link mt-1 inline-flex items-center gap-1.5 text-sm">
                        <x-icon name="external-link" class="h-4 w-4" />
                        Open Drive folder
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
