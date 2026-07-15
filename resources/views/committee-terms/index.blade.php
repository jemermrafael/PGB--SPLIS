@extends('layouts.app')

@section('title', 'Committee Terms — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Election Terms</h1>
            <p class="splis-page-subtitle">Track committee rosters per election period. Mark one term as current for new assignments.</p>
        </div>
        @can('create', App\Models\CommitteeTerm::class)
            <a href="{{ route('committee-terms.create') }}" class="splis-btn-primary">Add Term</a>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('committees.index') }}" class="splis-btn-secondary">Committees</a>
        <a href="{{ route('board-members.index') }}" class="splis-btn-secondary">Board Members</a>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Term</th>
                    <th>Years</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($terms as $term)
                    <tr>
                        <td class="font-medium text-slate-900 dark:text-slate-100">{{ $term->label }}</td>
                        <td>
                            @if ($term->year_from || $term->year_to)
                                {{ $term->year_from ?? '?' }}–{{ $term->year_to ?? 'present' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($term->is_current)
                                <span class="splis-badge-linked">Current</span>
                            @else
                                <span class="splis-badge-unlinked">Past</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('update', $term)
                                <a href="{{ route('committee-terms.edit', $term) }}" class="splis-btn-secondary text-sm">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-slate-500">No terms yet. Add the current election period to start tracking roster history.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($terms->hasPages())
        <div class="mt-4">{{ $terms->links() }}</div>
    @endif
</div>
@endsection
