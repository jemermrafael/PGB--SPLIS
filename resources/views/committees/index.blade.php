@extends('layouts.app')

@section('title', 'Committees — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header">
        <x-page-heading
            title="Committees"
            subtitle="Sangguniang Panlalawigan standing committees — used for Agenda Referral and Order of Business grouping."
            icon="meeting"
        />
        @can('create', App\Models\Committee::class)
            <a href="{{ route('committees.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
                Add Committee
            </a>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('board-members.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="users" class="h-4 w-4" />
            Board Members
        </a>
        <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
            <x-icon name="calendar" class="h-4 w-4" />
            Election Terms
        </a>
    </div>

    @include('partials.term-switcher', [
        'terms' => $terms,
        'selectedTerm' => $selectedTerm,
        'routeName' => 'committees.index',
    ])

    <div class="splis-table-wrap" data-drag-scroll>
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
                    @php
                        $allowLegacy = $selectedTerm->is_current;
                        $chairName = $committee->chairDisplayName($selectedTerm->id, $allowLegacy);
                        $viceChairName = $committee->viceChairDisplayName($selectedTerm->id, $allowLegacy);
                    @endphp
                    <tr>
                        <td class="text-slate-500">{{ $committee->sort_order }}</td>
                        <td>
                            <a
                                href="{{ route('committees.show', ['committee' => $committee, 'term' => $selectedTerm->id]) }}"
                                class="inline-flex max-w-full hover:opacity-90"
                            >
                                <x-committee-meta :committee="$committee" class="splis-list-committee--lg !normal-case tracking-normal" />
                            </a>
                        </td>
                        <td class="hidden lg:table-cell">
                            @if ($chairName)
                                <span class="splis-list-meta">
                                    <span class="splis-list-meta-avatar" aria-hidden="true">
                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                    </span>
                                    <span class="splis-list-meta-text">{{ $chairName }}</span>
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="hidden xl:table-cell">
                            @if ($viceChairName)
                                <span class="splis-list-meta">
                                    <span class="splis-list-meta-avatar splis-list-meta-avatar--muted" aria-hidden="true">
                                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                    </span>
                                    <span class="splis-list-meta-text">{{ $viceChairName }}</span>
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($committee->is_active)
                                <span class="splis-badge-linked splis-badge-with-icon">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Active
                                </span>
                            @else
                                <span class="splis-badge-unlinked splis-badge-with-icon">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('committees.show', ['committee' => $committee, 'term' => $selectedTerm->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-sm">
                                    <x-icon name="eye" class="h-4 w-4" />
                                    Roster
                                </a>
                                @can('update', $committee)
                                    <a href="{{ route('committees.edit', ['committee' => $committee, 'term' => $selectedTerm->id]) }}" class="splis-btn-secondary inline-flex items-center gap-2 text-sm">
                                        <x-icon name="edit" class="h-4 w-4" />
                                        Edit
                                    </a>
                                @endcan
                                @can('delete', $committee)
                                    <form
                                        method="POST"
                                        action="{{ route('committees.destroy', $committee) }}"
                                        data-confirm-submit
                                        data-confirm-title="Move committee to trash?"
                                        data-confirm-message="Move this committee to trash? Superadmin can restore from Trash."
                                        data-confirm-label="Delete"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="splis-btn-danger inline-flex items-center gap-2 text-sm">
                                            <x-icon name="trash" class="h-4 w-4" />
                                            Delete
                                        </button>
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
