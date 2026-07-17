@extends('layouts.app')

@section('title', $reference->title.' — Reference Materials — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <x-page-header
        :title="$reference->title"
        :subtitle="$reference->reference_no ? 'Reference no.: '.$reference->reference_no : null"
    >
        <x-slot:badges>
            <span class="splis-badge">{{ $reference->documentTypeLabel() }}</span>
            @if ($reference->status === 'active')
                <span class="splis-badge-linked">{{ $reference->statusLabel() }}</span>
            @elseif ($reference->status === 'archived')
                <span class="splis-badge-unlinked">{{ $reference->statusLabel() }}</span>
            @else
                <span class="splis-badge">{{ $reference->statusLabel() }}</span>
            @endif
        </x-slot:badges>
        <x-slot:meta>
            <div class="flex flex-wrap justify-end gap-2">
                @if ($reference->hasFile() && $reference->isPdf())
                    <button type="button" id="reference-view-file-btn" class="splis-btn-primary inline-flex items-center gap-2 text-nowrap">
                        <x-icon name="eye" class="h-4 w-4" />
                        View file
                    </button>
                @endif
                @if ($reference->hasFile())
                    <a href="{{ route('references.download', $reference) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap">
                        <x-icon name="download" class="h-4 w-4" />
                        Download
                    </a>
                @endif
                @can('update', $reference)
                    <a href="{{ route('references.edit', $reference) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-nowrap">
                        <x-icon name="edit" class="h-4 w-4" />
                        Edit
                    </a>
                @endcan
                <a href="{{ route('references.index') }}" class="splis-btn-ghost inline-flex items-center gap-2 text-nowrap">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back to list
                </a>
            </div>
        </x-slot:meta>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="splis-card lg:col-span-2">
            <div class="splis-card-header">
                <h2 class="splis-card-title">Document details</h2>
            </div>
            <dl class="splis-card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="splis-detail-label">Type</dt>
                    <dd class="mt-1 font-medium">{{ $reference->documentTypeLabel() }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Status</dt>
                    <dd class="mt-1 font-medium">{{ $reference->statusLabel() }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Issuing office</dt>
                    <dd class="mt-1 font-medium">{{ $reference->issuing_office ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Version</dt>
                    <dd class="mt-1 font-medium">{{ $reference->version_no ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Date issued</dt>
                    <dd class="mt-1 font-medium">{{ $reference->date_issued?->format('F j, Y') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="splis-detail-label">Effective date</dt>
                    <dd class="mt-1 font-medium">{{ $reference->effective_date?->format('F j, Y') ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="splis-detail-label">Keywords</dt>
                    <dd class="mt-1">{{ $reference->keywords ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="splis-detail-label">Summary</dt>
                    <dd class="mt-1 whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $reference->summary ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="splis-detail-label">Supersedes</dt>
                    <dd class="mt-1">
                        @if ($reference->supersedes)
                            <a class="splis-link" href="{{ route('references.show', $reference->supersedes) }}">{{ $reference->supersedes->title }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="splis-detail-label">Superseded by</dt>
                    <dd class="mt-1">
                        @if ($reference->supersededBy->isNotEmpty())
                            <ul class="list-disc pl-5">
                                @foreach ($reference->supersededBy as $child)
                                    <li><a class="splis-link" href="{{ route('references.show', $child) }}">{{ $child->title }}</a></li>
                                @endforeach
                            </ul>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        <div class="space-y-4">
            <div class="splis-card">
                <div class="splis-card-header">
                    <h2 class="splis-card-title">File</h2>
                </div>
                <div class="splis-card-body space-y-3 text-sm">
                    <p><span class="splis-detail-label">Filename:</span> {{ $reference->original_filename ?: '—' }}</p>
                    <p><span class="splis-detail-label">Type:</span> {{ $reference->mime_type ?: '—' }}</p>
                    <p><span class="splis-detail-label">Size:</span> {{ $reference->file_size ? number_format((int) $reference->file_size / 1024, 1).' KB' : '—' }}</p>
                    @unless ($reference->hasFile())
                        <p class="text-slate-500">No file uploaded yet.</p>
                    @endunless
                </div>
            </div>

            @can('archive', $reference)
                <div class="splis-card">
                    <div class="splis-card-header">
                        <h2 class="splis-card-title">Lifecycle</h2>
                    </div>
                    <div class="splis-card-body space-y-2">
                        @if ($reference->status !== 'archived')
                            <form
                                method="POST"
                                action="{{ route('references.archive', $reference) }}"
                                data-confirm-submit
                                data-confirm-title="Archive reference material?"
                                data-confirm-message="Archive {{ $reference->title }}? It can be restored later from Lifecycle."
                                data-confirm-label="Archive"
                                data-confirm-danger="0"
                            >
                                @csrf
                                <button type="submit" class="splis-btn-secondary inline-flex w-full items-center justify-center gap-2">
                                    <x-icon name="archive" class="h-4 w-4" />
                                    Archive
                                </button>
                            </form>
                        @else
                            <form
                                method="POST"
                                action="{{ route('references.restore', $reference) }}"
                                data-confirm-submit
                                data-confirm-title="Restore reference material?"
                                data-confirm-message="Restore {{ $reference->title }} to active status?"
                                data-confirm-label="Restore"
                                data-confirm-danger="0"
                            >
                                @csrf
                                <button type="submit" class="splis-btn-secondary inline-flex w-full items-center justify-center gap-2">
                                    <x-icon name="check-circle" class="h-4 w-4" />
                                    Restore
                                </button>
                            </form>
                        @endif
                        @can('delete', $reference)
                            <form
                                method="POST"
                                action="{{ route('references.destroy', $reference) }}"
                                data-confirm-submit
                                data-confirm-title="Move reference material to trash?"
                                data-confirm-message="Move {{ $reference->title }} to trash? Superadmin can restore from Trash."
                                data-confirm-label="Delete"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="splis-btn-danger inline-flex w-full items-center justify-center gap-2">
                                    <x-icon name="trash" class="h-4 w-4" />
                                    Delete
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endcan
        </div>
    </div>

    <div class="mt-6 splis-card">
        <div class="splis-card-header">
            <h2 class="splis-card-title">Version history</h2>
        </div>
        <div class="splis-card-body">
            <div class="splis-table-wrap" data-drag-scroll>
                <table class="splis-table">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th class="hidden md:table-cell">Filename</th>
                            <th class="hidden lg:table-cell">Uploaded by</th>
                            <th class="hidden sm:table-cell">Uploaded at</th>
                            <th>Text index</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reference->versions as $version)
                            <tr>
                                <td>v{{ $version->version_no }}</td>
                                <td class="hidden md:table-cell">{{ $version->original_filename ?: basename($version->file_path) }}</td>
                                <td class="hidden lg:table-cell">{{ $version->creator?->name ?: 'System' }}</td>
                                <td class="hidden sm:table-cell whitespace-nowrap">{{ $version->created_at?->format('M d, Y g:i A') ?: '—' }}</td>
                                <td>
                                    @if (filled($version->extracted_text))
                                        <span class="splis-badge-linked">Indexed</span>
                                    @else
                                        <span class="splis-badge-unlinked">Not indexed</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="splis-link inline-flex items-center gap-1.5" href="{{ route('references.versions.download', ['reference' => $reference, 'version' => $version]) }}">
                                        <x-icon name="download" class="h-4 w-4" />
                                        Download
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-500">No file versions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('partials.detail-prev-next', [
        'previous' => $previousReference ?? null,
        'next' => $nextReference ?? null,
        'previousUrl' => ($previousReference ?? null) ? route('references.show', $previousReference) : null,
        'nextUrl' => ($nextReference ?? null) ? route('references.show', $nextReference) : null,
        'previousLabel' => isset($previousReference) ? \Illuminate\Support\Str::limit($previousReference->title, 60) : null,
        'nextLabel' => isset($nextReference) ? \Illuminate\Support\Str::limit($nextReference->title, 60) : null,
        'label' => 'Reference material navigation',
    ])
</div>

@if ($reference->hasFile() && $reference->isPdf())
    <div id="reference-pdf-modal" class="splis-modal" hidden>
        <div class="splis-modal-backdrop" data-reference-modal-close tabindex="-1" aria-hidden="true"></div>
        <div class="splis-modal-panel !max-h-[92vh] !max-w-5xl" data-reference-pdf-panel role="dialog" aria-modal="true" aria-labelledby="reference-pdf-modal-title">
            <div class="splis-modal-header">
                <h3 id="reference-pdf-modal-title" class="splis-modal-title">{{ $reference->original_filename ?: $reference->title }}</h3>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        id="reference-pdf-fullscreen"
                        class="splis-btn-ghost inline-flex items-center gap-1.5 !px-2 !py-1 text-xs"
                        aria-pressed="false"
                    >
                        <x-icon name="maximize" class="h-3.5 w-3.5" data-fullscreen-icon="enter" />
                        <x-icon name="minimize" class="hidden h-3.5 w-3.5" data-fullscreen-icon="exit" />
                        <span data-fullscreen-label>Fullscreen</span>
                    </button>
                    <button type="button" class="splis-modal-close" data-reference-modal-close aria-label="Close">×</button>
                </div>
            </div>
            <div class="splis-modal-body !p-0">
                <iframe
                    id="reference-pdf-frame"
                    title="Reference material PDF preview"
                    class="splis-pdf-modal-frame block h-[75vh] w-full border-0 bg-slate-100 dark:bg-slate-900"
                    src="about:blank"
                    data-src="{{ route('references.view', $reference) }}"
                ></iframe>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('reference-pdf-modal');
            const panel = modal?.querySelector('[data-reference-pdf-panel]');
            const openBtn = document.getElementById('reference-view-file-btn');
            const frame = document.getElementById('reference-pdf-frame');
            const fullscreenBtn = document.getElementById('reference-pdf-fullscreen');

            if (! modal || ! panel || ! openBtn || ! frame) {
                return;
            }

            function setFullscreen(enabled) {
                modal.classList.toggle('is-fullscreen-active', enabled);
                panel.classList.toggle('is-fullscreen', enabled);

                if (fullscreenBtn) {
                    fullscreenBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                    const label = fullscreenBtn.querySelector('[data-fullscreen-label]');
                    const enterIcon = fullscreenBtn.querySelector('[data-fullscreen-icon="enter"]');
                    const exitIcon = fullscreenBtn.querySelector('[data-fullscreen-icon="exit"]');

                    if (label) {
                        label.textContent = enabled ? 'Exit fullscreen' : 'Fullscreen';
                    }
                    enterIcon?.classList.toggle('hidden', enabled);
                    exitIcon?.classList.toggle('hidden', ! enabled);
                }
            }

            function openModal() {
                if (frame.getAttribute('src') === 'about:blank') {
                    frame.setAttribute('src', frame.dataset.src);
                }

                setFullscreen(false);
                modal.hidden = false;
                document.body.classList.add('splis-modal-open');
            }

            function closeModal() {
                setFullscreen(false);
                modal.hidden = true;
                document.body.classList.remove('splis-modal-open');
            }

            openBtn.addEventListener('click', openModal);
            fullscreenBtn?.addEventListener('click', () => {
                setFullscreen(! panel.classList.contains('is-fullscreen'));
            });
            modal.querySelectorAll('[data-reference-modal-close]').forEach((el) => {
                el.addEventListener('click', closeModal);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && ! modal.hidden) {
                    if (panel.classList.contains('is-fullscreen')) {
                        setFullscreen(false);
                        return;
                    }
                    closeModal();
                }
            });
        })();
    </script>
    @endpush
@endif
@endsection
