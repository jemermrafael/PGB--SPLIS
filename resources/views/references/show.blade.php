@extends('layouts.app')

@section('title', $reference->title.' — Reference Materials — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="splis-badge">{{ $reference->documentTypeLabel() }}</span>
                @if ($reference->status === 'active')
                    <span class="splis-badge-linked">{{ $reference->statusLabel() }}</span>
                @elseif ($reference->status === 'archived')
                    <span class="splis-badge-unlinked">{{ $reference->statusLabel() }}</span>
                @else
                    <span class="splis-badge">{{ $reference->statusLabel() }}</span>
                @endif
            </div>
            <h1 class="splis-page-title">{{ $reference->title }}</h1>
            @if ($reference->reference_no)
                <p class="splis-page-subtitle">Reference no.: {{ $reference->reference_no }}</p>
            @endif
        </div>
    </div>
    <div class="splis-page-header">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
               
            </div>
        </div>
        <div class="flex flex-nowrap justify-end gap-2">
            @if ($reference->hasFile())
                <a href="{{ route('references.download', $reference) }}" class="splis-btn-primary text-nowrap">Download file</a>
            @endif
            @can('update', $reference)
                <a href="{{ route('references.edit', $reference) }}" class="splis-btn-secondary text-nowrap">Edit</a>
            @endcan
            <a href="{{ route('references.index') }}" class="splis-btn-secondary text-nowrap">Back to list</a>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

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
                    @if ($reference->hasFile())
                        <div class="flex flex-wrap gap-2 pt-1">
                            @if ($reference->isPdf())
                                <button type="button" id="reference-view-file-btn" class="splis-btn-primary text-nowrap w-full">
                                    View file
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @can('archive', $reference)
                <div class="splis-card">
                    <div class="splis-card-header">
                        <h2 class="splis-card-title">Lifecycle</h2>
                    </div>
                    <div class="splis-card-body space-y-2">
                        @if ($reference->status !== 'archived')
                            <form method="POST" action="{{ route('references.archive', $reference) }}" onsubmit="return confirm('Archive this reference material?')">
                                @csrf
                                <button type="submit" class="splis-btn-secondary w-full">Archive</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('references.restore', $reference) }}" onsubmit="return confirm('Restore this reference material?')">
                                @csrf
                                <button type="submit" class="splis-btn-secondary w-full">Restore</button>
                            </form>
                        @endif
                        @can('delete', $reference)
                            <form method="POST" action="{{ route('references.destroy', $reference) }}" onsubmit="return confirm('Delete this reference material?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="splis-btn-ghost w-full text-red-600">Delete</button>
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
            <div class="splis-table-wrap">
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
                                    <a class="splis-link" href="{{ route('references.versions.download', ['reference' => $reference, 'version' => $version]) }}">
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
</div>

@if ($reference->hasFile() && $reference->isPdf())
    <div id="reference-pdf-modal" class="splis-modal" hidden>
        <div class="splis-modal-backdrop" data-reference-modal-close tabindex="-1" aria-hidden="true"></div>
        <div class="splis-modal-panel !max-h-[92vh] !max-w-5xl" role="dialog" aria-modal="true" aria-labelledby="reference-pdf-modal-title">
            <div class="splis-modal-header">
                <h3 id="reference-pdf-modal-title" class="splis-modal-title">{{ $reference->original_filename ?: $reference->title }}</h3>
                <button type="button" class="splis-modal-close" data-reference-modal-close aria-label="Close">×</button>
            </div>
            <div class="splis-modal-body !p-0">
                <iframe
                    id="reference-pdf-frame"
                    title="Reference material PDF preview"
                    class="block h-[75vh] w-full border-0 bg-slate-100 dark:bg-slate-900"
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
            const openBtn = document.getElementById('reference-view-file-btn');
            const frame = document.getElementById('reference-pdf-frame');

            if (! modal || ! openBtn || ! frame) {
                return;
            }

            function openModal() {
                if (frame.getAttribute('src') === 'about:blank') {
                    frame.setAttribute('src', frame.dataset.src);
                }

                modal.hidden = false;
                document.body.classList.add('splis-modal-open');
            }

            function closeModal() {
                modal.hidden = true;
                document.body.classList.remove('splis-modal-open');
            }

            openBtn.addEventListener('click', openModal);
            modal.querySelectorAll('[data-reference-modal-close]').forEach((el) => {
                el.addEventListener('click', closeModal);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && ! modal.hidden) {
                    closeModal();
                }
            });
        })();
    </script>
    @endpush
@endif
@endsection

