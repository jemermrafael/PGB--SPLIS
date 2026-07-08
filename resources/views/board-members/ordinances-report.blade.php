@extends('layouts.app')

@section('title', 'Board member ordinances — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Board member authored ordinances</h1>
            <p class="splis-page-subtitle">Provincial ordinances by board member — passed or pending.</p>
        </div>
    </div>

    <form method="GET" class="splis-card splis-card-body mb-6 flex flex-wrap items-end gap-4">
        <div class="min-w-[16rem] flex-1">
            <label class="splis-label" for="board_member_id">Board member</label>
            <select name="board_member_id" id="board_member_id" class="splis-select" required>
                <option value="">Select board member</option>
                @foreach ($boardMembers as $member)
                    <option value="{{ $member->id }}" @selected($selectedMember?->id === $member->id)>{{ $member->displayName() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="splis-btn-primary">View ordinances</button>
    </form>

    @if ($selectedMember)
        <div class="splis-card overflow-hidden">
            <div class="splis-card-header">
                <h2 class="splis-card-title">{{ $selectedMember->displayName() }}</h2>
                <p class="splis-card-subtitle">{{ $ordinances instanceof \Illuminate\Pagination\LengthAwarePaginator ? $ordinances->total() : $ordinances->count() }} ordinance(s)</p>
            </div>
            <div class="splis-table-wrap">
                <table class="splis-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Subject</th>
                            <th>Date enacted</th>
                            <th>Date approved</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ordinances as $ordinance)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('ordinances.show', $ordinance) }}" class="splis-link">{{ $ordinance->displayNumber() }} ({{ $ordinance->series_year }})</a>
                                </td>
                                <td>{{ $ordinance->shortSubject(100) }}</td>
                                <td class="whitespace-nowrap">{{ $ordinance->date_enacted?->format('M j, Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $ordinance->date_approved?->format('M j, Y') ?? '—' }}</td>
                                <td>
                                    @if ($ordinance->date_enacted)
                                        <span class="splis-badge-linked">Passed</span>
                                    @else
                                        <span class="splis-badge">Not passed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-slate-500">No authored ordinances found for this board member.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($ordinances instanceof \Illuminate\Pagination\LengthAwarePaginator && $ordinances->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                    {{ $ordinances->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
