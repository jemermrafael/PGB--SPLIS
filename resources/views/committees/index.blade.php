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
                Add committee
            </a>
        @endcan
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

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
                    <tr>
                        <td class="text-slate-500">{{ $committee->sort_order }}</td>
                        <td class="font-medium text-slate-900 dark:text-slate-100">{{ $committee->name }}</td>
                        <td class="hidden lg:table-cell">{{ $committee->chair ?: '—' }}</td>
                        <td class="hidden xl:table-cell">{{ $committee->vice_chair ?: '—' }}</td>
                        <td>
                            @if ($committee->is_active)
                                <span class="splis-badge-linked">Active</span>
                            @else
                                <span class="splis-badge-unlinked">Inactive</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                @can('update', $committee)
                                    <a href="{{ route('committees.edit', $committee) }}" class="splis-btn-secondary text-sm">Edit</a>
                                @endcan
                                @can('delete', $committee)
                                    <form method="POST" action="{{ route('committees.destroy', $committee) }}" onsubmit="return confirm('Delete this committee?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="splis-btn-ghost text-sm text-red-600">Delete</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-slate-500">
                            No committees yet. Run <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">php artisan splis:import-committees</code> or add one manually.
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
