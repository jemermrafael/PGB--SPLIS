@extends('layouts.app')

@section('title', 'Committees — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Committees</h1>
            <p class="splis-page-subtitle">Sangguniang Panlalawigan standing committees — used for agenda referral and Order of Business grouping.</p>
        </div>
        @can('create', App\Models\Committee::class)
            <a href="{{ route('committees.create') }}" class="splis-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Add Committee
            </a>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('board-members.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-secondary">Board Members</a>
        <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary">Election Terms</a>
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($terms as $term)
            <a
                href="{{ route('committees.index', ['term' => $term->id]) }}"
                class="{{ $term->id === $selectedTerm->id ? 'splis-btn-primary' : 'splis-btn-secondary' }} text-sm"
            >
                {{ $term->label }}@if ($term->is_current) (current)@endif
            </a>
        @endforeach
    </div>

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th class="w-12">No.</th>
                    <th>Committee</th>
                    <th class="hidden lg:table-cell">Chair</th>
                    <th class="hidden xl:table-cell">Vice chair</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($committees as $committee)
                    @php $allowLegacy = $selectedTerm->is_current; @endphp
                    <tr>
                        <td class="text-slate-500">{{ $committee->sort_order }}</td>
                        <td class="font-medium text-slate-900 dark:text-slate-100">
                            <a href="{{ route('committees.show', ['committee' => $committee, 'term' => $selectedTerm->id]) }}" class="hover:text-brand-700 dark:hover:text-brand-300">{{ $committee->name }}</a>
                        </td>
                        <td class="hidden lg:table-cell">{{ $committee->chairDisplayName($selectedTerm->id, $allowLegacy) ?: '—' }}</td>
                        <td class="hidden xl:table-cell">{{ $committee->viceChairDisplayName($selectedTerm->id, $allowLegacy) ?: '—' }}</td>
                        <td>
                            @if ($committee->is_active)
                                <span class="splis-badge-linked">Active</span>
                            @else
                                <span class="splis-badge-unlinked">Inactive</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('committees.show', ['committee' => $committee, 'term' => $selectedTerm->id]) }}" class="splis-btn-secondary text-sm">Roster</a>
                                @can('update', $committee)
                                    <a href="{{ route('committees.edit', ['committee' => $committee, 'term' => $selectedTerm->id]) }}" class="splis-btn-secondary text-sm">Edit</a>
                                @endcan
                                @can('delete', $committee)
                                    <form method="POST" action="{{ route('committees.destroy', $committee) }}" onsubmit="return confirm('Move this committee to trash?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="splis-btn-danger text-sm">Move to trash</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-slate-500">
                            @if ($selectedTerm->is_current)
                                No committees yet. Run <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">php artisan splis:import-committees</code> or add one manually.
                            @else
                                No committee rosters for {{ $selectedTerm->label }} yet. Edit a committee and save its roster for this term.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($committees->hasPages())
        <div class="mt-4">{{ $committees->links() }}</div>
    @endif
</div>
@endsection
