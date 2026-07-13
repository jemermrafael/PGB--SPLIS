@extends('layouts.app')

@section('title', 'Reference Materials — '.config('app.name'))

@section('content')
<div class="max-w-7xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Reference Materials</h1>
            <p class="splis-page-subtitle">Digital compendium of guidelines, memoranda, circulars, issuances, manuals, and related references.</p>
        </div>
        @can('create', App\Models\ReferenceMaterial::class)
            <a href="{{ route('references.create') }}" class="splis-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
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

    <div class="mt-6 splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th class="hidden lg:table-cell">Type</th>
                    <th class="hidden xl:table-cell">Office</th>
                    <th class="hidden md:table-cell">Issued</th>
                    <th>Status</th>
                    <th>File</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($materials as $material)
                    <tr>
                        <td>
                            <a href="{{ route('references.show', $material) }}" class="splis-table-title splis-table-title--list">{{ $material->title }}</a>
                            <p class="text-xs text-slate-500">
                                {{ $material->reference_no ?: 'No reference no.' }}
                                @if ($material->version_no)
                                    · v{{ $material->version_no }}
                                @endif
                            </p>
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
                        <td>
                            @if ($material->hasFile())
                                <a href="{{ route('references.download', $material) }}" class="splis-link">Download</a>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-slate-500">No reference materials found.</td>
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

