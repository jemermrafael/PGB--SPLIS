@extends('layouts.app')

@section('title', $ordinance->displayHeading().' — '.$ordinance->displaySeries().' — Ordinances — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <div>
            @if ($ordinance->publishedFromAgenda)
                <div class="mb-2">
                    <a href="{{ route('agenda.show', $ordinance->publishedFromAgenda) }}" class="splis-badge-linked">
                        Published from Agenda {{ $ordinance->publishedFromAgenda->id }} · Series {{ $ordinance->publishedFromAgenda->reso_ord_ao_series ?: $ordinance->series_year }}
                    </a>
                </div>
            @endif
            <h1 class="splis-page-title">{{ $ordinance->displayHeading() }}</h1>
            <p class="splis-page-subtitle">{{ $ordinance->displaySeries() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $ordinance)
                <a href="{{ route('ordinances.edit', $ordinance) }}" class="splis-btn-primary inline-flex items-center gap-2">
                    <x-icon name="edit" class="h-4 w-4" />
                    Edit
                </a>
                @if ($ordinance->missingPdfMirrorTypes() !== [])
                    <form method="POST" action="{{ route('ordinances.mirror-pdf', $ordinance) }}">
                        @csrf
                        <button type="submit" class="splis-btn-secondary inline-flex items-center gap-2">
                            <x-icon name="download" class="h-4 w-4" />
                            Download PDFs from Drive
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if ($ordinance->publication_status)
        <div class="mb-6">
            @include('partials.ordinance-publication-button', ['status' => $ordinance->publication_status])
        </div>
    @endif

    <div class="splis-card splis-card-body mb-6 space-y-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Title</p>
                <p class="mt-1 text-slate-900 dark:text-slate-100">{{ $ordinance->title ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</p>
                <p class="mt-1 whitespace-pre-wrap text-slate-900 dark:text-slate-100">{{ $ordinance->subject ?: '—' }}</p>
            </div>

        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @include('ordinances.partials.board-member-attribution', ['ordinance' => $ordinance])
            <div>
                <dt class="splis-label">Date enacted</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $ordinance->date_enacted?->format('F j, Y') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="splis-label">Date approved</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $ordinance->date_approved?->format('F j, Y') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="splis-label">Posted in conspicuous places</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $ordinance->date_posted?->format('F j, Y') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="splis-label">Published in newspaper</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $ordinance->date_published_newspaper?->format('F j, Y') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="splis-label">Effectivity date</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $ordinance->effectivity_date?->format('F j, Y') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="splis-label">Classification</dt>
                <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $ordinance->classification ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="splis-card splis-card-body mb-6 space-y-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Means of verification</h2>
        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <dt class="splis-label">Bulletin</dt>
                <dd class="mt-1 space-y-2">
                    @if ($ordinance->mov_bulletin)
                        <p class="whitespace-pre-wrap text-slate-900 dark:text-slate-100">{{ $ordinance->mov_bulletin }}</p>
                    @else
                        <p class="text-slate-500">—</p>
                    @endif
                    @if ($ordinance->movBulletinPdfPublicUrl())
                        @include('partials.pdf-modal-trigger', [
                            'url' => $ordinance->movBulletinPdfPublicUrl(),
                            'viewer' => $ordinance->movBulletinViewerMode(),
                            'title' => 'Bulletin — '.$ordinance->displayNumber(),
                            'label' => 'View Bulletin',
                        ])
                    @endif
                </dd>
            </div>
            <div>
                <dt class="splis-label">Certification</dt>
                <dd class="mt-1 space-y-2">
                    @if ($ordinance->mov_certification)
                        <p class="text-slate-900 dark:text-slate-100">{{ $ordinance->mov_certification }}</p>
                    @else
                        <p class="text-slate-500">—</p>
                    @endif
                    @if ($ordinance->movCertificationPdfPublicUrl())
                        @include('partials.pdf-modal-trigger', [
                            'url' => $ordinance->movCertificationPdfPublicUrl(),
                            'viewer' => $ordinance->movCertificationViewerMode(),
                            'title' => 'Certification — '.$ordinance->displayNumber(),
                            'label' => 'View Certification',
                        ])
                    @endif
                </dd>
            </div>
            <div class="md:col-span-2">
                <dt class="splis-label">Newspaper</dt>
                <dd class="mt-1 space-y-2">
                    @if ($ordinance->mov_newspaper)
                        <p class="text-slate-900 dark:text-slate-100">{{ $ordinance->mov_newspaper }}</p>
                    @else
                        <p class="text-slate-500">—</p>
                    @endif
                    @if ($ordinance->movNewspaperPdfPublicUrl())
                        @include('partials.pdf-modal-trigger', [
                            'url' => $ordinance->movNewspaperPdfPublicUrl(),
                            'viewer' => $ordinance->movNewspaperViewerMode(),
                            'title' => 'Newspaper — '.$ordinance->displayNumber(),
                            'label' => 'View Newspaper',
                        ])
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    <div class="splis-card splis-card-body space-y-4">
        <div>
            <dt class="splis-label">Implementing bodies / departments</dt>
            <dd class="mt-1 whitespace-pre-wrap text-slate-900 dark:text-slate-100">{{ $ordinance->implementing_bodies ?: '—' }}</dd>
        </div>
        <div>
            <dt class="splis-label">Mandate / PPA</dt>
            <dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $ordinance->mandate_ppa ?: '—' }}</dd>
        </div>
        <div>
            <dt class="splis-label">Remarks</dt>
            <dd class="mt-1 whitespace-pre-wrap text-slate-900 dark:text-slate-100">{{ $ordinance->remarks ?: '—' }}</dd>
        </div>
    </div>

    @include('partials.pdf-document-embed', [
        'pdfUrl' => $ordinance->pdfPublicUrl(),
        'viewer' => $ordinance->pdfViewerMode() ?? 'embed',
        'embedTitle' => $ordinance->displayNumber().' PDF',
    ])

    @include('ordinances.partials.version-history', ['ordinance' => $ordinance])

    @include('partials.detail-prev-next', [
        'previous' => $previousOrdinance ?? null,
        'next' => $nextOrdinance ?? null,
        'previousUrl' => ($previousOrdinance ?? null) ? route('ordinances.show', $previousOrdinance) : null,
        'nextUrl' => ($nextOrdinance ?? null) ? route('ordinances.show', $nextOrdinance) : null,
        'previousLabel' => isset($previousOrdinance) ? $previousOrdinance->displayNumber().' · '.$previousOrdinance->displaySeries() : null,
        'nextLabel' => isset($nextOrdinance) ? $nextOrdinance->displayNumber().' · '.$nextOrdinance->displaySeries() : null,
        'label' => 'Ordinance navigation',
    ])

    <div class="mt-6 flex flex-wrap gap-2">
        <a href="{{ route('ordinances.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Back to Ordinances
        </a>
        @can('delete', $ordinance)
            <form
                method="POST"
                action="{{ route('ordinances.destroy', $ordinance) }}"
                data-confirm-submit
                data-confirm-title="Move Ordinance to trash?"
                data-confirm-message="Move this Ordinance to trash? Superadmin can restore from Trash."
                data-confirm-label="Delete"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="splis-btn-danger inline-flex items-center gap-2">
                    <x-icon name="trash" class="h-4 w-4" />
                    Delete
                </button>
            </form>
        @endcan
    </div>
</div>
@endsection
