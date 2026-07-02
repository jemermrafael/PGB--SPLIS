@extends('layouts.app')

@section('title', 'Board Members — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Board members</h1>
            <p class="splis-page-subtitle">Sangguniang Panlalawigan roster — Vice Governor, district board members, and ex officio members.</p>
        </div>
        @can('create', App\Models\BoardMember::class)
            <a href="{{ route('board-members.create') }}" class="splis-btn-primary">Add board member</a>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('committees.index') }}" class="splis-btn-secondary">Committees</a>
        <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary">Election terms</a>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    @forelse ($boardMembersByDistrict as $district => $members)
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $district }}</h2>
            <div class="splis-table-wrap">
                <table class="splis-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr>
                                <td class="font-medium text-slate-900 dark:text-slate-100">
                                    <a href="{{ route('board-members.show', $member) }}" class="hover:text-brand-700 dark:hover:text-brand-300">
                                        {{ $member->displayName() }}
                                    </a>
                                </td>
                                <td>
                                    @if ($member->is_active)
                                        <span class="splis-badge-linked">Active</span>
                                    @else
                                        <span class="splis-badge-unlinked">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('board-members.show', $member) }}" class="splis-btn-secondary text-sm">Profile</a>
                                        @can('update', $member)
                                            <a href="{{ route('board-members.edit', $member) }}" class="splis-btn-secondary text-sm">Edit</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="splis-card splis-card-body py-10 text-center text-slate-500">
            No board members yet. Add personnel manually or run <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">php artisan splis:import-committees</code>.
        </div>
    @endforelse
</div>
@endsection
