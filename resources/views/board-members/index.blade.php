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
            <a href="{{ route('board-members.create', ['term' => $selectedTerm->id]) }}" class="splis-btn-primary">Add board member</a>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('committees.index', ['term' => $selectedTerm->id]) }}" class="splis-btn-secondary">Committees</a>
        <a href="{{ route('committee-terms.index') }}" class="splis-btn-secondary">Election terms</a>
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($terms as $term)
            <a
                href="{{ route('board-members.index', ['term' => $term->id]) }}"
                class="{{ $term->id === $selectedTerm->id ? 'splis-btn-primary' : 'splis-btn-secondary' }} text-sm"
            >
                {{ $term->label }}@if ($term->is_current) (current)@endif
            </a>
        @endforeach
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    @forelse ($boardMembersByDistrict as $district => $assignments)
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
                        @foreach ($assignments as $assignment)
                            @php $member = $assignment->boardMember; @endphp
                            @if ($member)
                                <tr>
                                    <td class="font-medium text-slate-900 dark:text-slate-100">
                                        <a href="{{ route('board-members.show', ['boardMember' => $member, 'term' => $selectedTerm->id]) }}" class="hover:text-brand-700 dark:hover:text-brand-300">
                                            {{ $member->displayName() }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($assignment->is_active)
                                            <span class="splis-badge-linked">Active</span>
                                        @else
                                            <span class="splis-badge-unlinked">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('board-members.show', ['boardMember' => $member, 'term' => $selectedTerm->id]) }}" class="splis-btn-secondary text-sm">Profile</a>
                                            @can('update', $member)
                                                <a href="{{ route('board-members.edit', ['boardMember' => $member, 'term' => $selectedTerm->id]) }}" class="splis-btn-secondary text-sm">Edit</a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="splis-card splis-card-body py-10 text-center text-slate-500">
            No board members for {{ $selectedTerm->label }} yet. Add personnel for this term or switch to another election period.
        </div>
    @endforelse
</div>
@endsection
