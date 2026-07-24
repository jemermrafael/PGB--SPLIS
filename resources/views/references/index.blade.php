@extends('layouts.app')

@section('title', 'Reference Materials — '.config('app.name'))

@section('content')
<div class="max-w-7xl">
    <div class="splis-page-header">
        <x-page-heading
            title="Reference Materials"
            subtitle="Digital Compendium of Guidelines, Memoranda, Circulars, Issuances, Manuals, and Related References."
            icon="book"
        />
        @can('create', App\Models\ReferenceMaterial::class)
            <a href="{{ route('references.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                Add Reference
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('references.index') }}" class="splis-filter-panel">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Search Reference Library</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label class="splis-label">Keyword</label>
                <input type="text" name="q" class="splis-input" value="{{ $filters['q'] ?? '' }}" placeholder="Title, office, reference no., keywords">
            </div>
            <div>
                <label class="splis-label">Type</label>
                <select name="document_type" class="splis-select">
                    <option value="">All types</option>
                    @foreach ($documentTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['document_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Status</label>
                <select name="status" class="splis-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label">Year issued</label>
                <select name="year" class="splis-select">
                    <option value="">All years</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="xl:col-span-2">
                <label class="splis-label">Issuing office</label>
                <select name="issuing_office" class="splis-select">
                    <option value="">All offices</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office }}" @selected(($filters['issuing_office'] ?? '') === $office)>{{ $office }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Search</button>
            <a href="{{ route('references.index') }}" class="splis-btn-ghost">Clear filters</a>
        </div>
    </form>

    {{-- Mobile / tablet cards --}}
    <div class="mt-6 splis-mobile-card-list md:hidden">
        @forelse ($materials as $material)
            <article class="splis-mobile-card">
                <a href="{{ route('references.show', $material) }}" class="splis-mobile-card-title">{{ $material->title }}</a>
                <div class="splis-mobile-card-meta">
                    <p>{{ $material->documentTypeLabel() }} · {{ $material->statusLabel() }}</p>
                    <p>{{ $material->reference_no ?: 'No reference no.' }}@if ($material->version_no) · v{{ $material->version_no }}@endif</p>
                    <p>{{ $material->issuing_office ?: 'No issuing office' }}</p>
                    <p>Issued {{ $material->date_issued?->format('M d, Y') ?: '—' }}</p>
                    @if (filled($material->summary))
                        <p class="mt-1">{{ \Illuminate\Support\Str::words($material->summary, 20, '…') }}</p>
                    @endif
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <a href="{{ route('references.show', $material) }}" class="splis-link text-sm">Open</a>
                    @if ($material->hasFile() && $material->isPdf())
                        @include('partials.pdf-modal-trigger', [
                            'url' => route('references.view', $material),
                            'title' => $material->title.' PDF',
                            'label' => 'View PDF',
                            'icon' => 'book-closed',
                            'class' => 'splis-link text-sm inline-flex items-center gap-1',
                        ])
                    @elseif ($material->hasFile())
                        <a href="{{ route('references.download', $material) }}" class="splis-link text-sm">Download</a>
                    @endif
                </div>
            </article>
        @empty
            <x-empty-state
                title="No reference materials found"
                description="Try clearing filters or add a new reference."
            >
                @can('create', App\Models\ReferenceMaterial::class)
                    <x-slot:actions>
                        <a href="{{ route('references.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                            <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                            Add Reference
                        </a>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="mt-6 hidden splis-table-wrap md:block" data-drag-scroll>
        <table class="splis-table">
            <thead>
                <tr>
                    <th class="w-12 text-center">File</th>
                    <th>Title</th>
                    <th class="min-w-[12rem] max-w-md">Summary</th>
                    <th class="hidden lg:table-cell">Type</th>
                    <th class="hidden xl:table-cell">Office</th>
                    <th class="hidden md:table-cell">Issued</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($materials as $material)
                    @php
                        $summaryFull = trim((string) ($material->summary ?? ''));
                        $summaryWords = $summaryFull === '' ? [] : (preg_split('/\s+/', $summaryFull) ?: []);
                        $summaryTruncated = count($summaryWords) > 20;
                        $summaryDisplay = $summaryTruncated
                            ? implode(' ', array_slice($summaryWords, 0, 20)).'…'
                            : ($summaryFull !== '' ? $summaryFull : '—');
                    @endphp
                    <tr>
                        <td class="text-center">
                            @if ($material->hasFile() && $material->isPdf())
                                @include('partials.pdf-modal-trigger', [
                                    'url' => route('references.view', $material),
                                    'title' => $material->title.' PDF',
                                    'label' => '',
                                    'icon' => 'book-closed',
                                    'class' => 'splis-doc-pdf-icon !text-brand-800 hover:!bg-brand-50',
                                    'ariaLabel' => 'View PDF: '.$material->title,
                                ])
                            @elseif ($material->hasFile())
                                <a
                                    href="{{ route('references.download', $material) }}"
                                    class="splis-doc-pdf-icon !text-brand-800 hover:!bg-brand-50"
                                    title="Download file"
                                    aria-label="Download file"
                                >
                                    <x-icon name="book-closed" class="h-4 w-4" />
                                </a>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('references.show', $material) }}" class="splis-link font-semibold">{{ $material->title }}</a>
                            <p class="text-xs text-slate-500">
                                {{ $material->reference_no ?: 'No reference no.' }}
                                @if ($material->version_no)
                                    · v{{ $material->version_no }}
                                @endif
                            </p>
                        </td>
                        <td class="max-w-md text-sm text-slate-600 dark:text-slate-300">
                            @if ($summaryTruncated)
                                <span class="splis-title-tip" data-full-title="{{ e($summaryFull) }}" tabindex="0">{{ $summaryDisplay }}</span>
                            @else
                                {{ $summaryDisplay }}
                            @endif
                        </td>
                        <td class="hidden lg:table-cell">{{ $material->documentTypeLabel() }}</td>
                        <td class="hidden xl:table-cell">{{ $material->issuing_office ?: '—' }}</td>
                        <td class="hidden md:table-cell whitespace-nowrap">{{ $material->date_issued?->format('M d, Y') ?: '—' }}</td>
                        <td>
                            @if ($material->status === 'active')
                                <span class="splis-badge-linked">{{ $material->statusLabel() }}</span>
                            @elseif ($material->status === 'archived')
                                <span class="splis-badge-unlinked">{{ $material->statusLabel() }}</span>
                            @else
                                <span class="splis-badge">{{ $material->statusLabel() }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-10 text-center text-slate-500">No reference materials found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($materials->hasPages())
        <div class="mt-4">{{ $materials->links() }}</div>
    @endif
</div>
@endsection
