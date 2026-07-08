@extends('layouts.app')

@section('title', 'Appropriation Ordinances — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Appropriation Ordinances</h1>
            <p class="splis-page-subtitle">Provincial appropriation ordinances — intake through SP passage and gubernatorial approval.</p>
        </div>
        @can('create', App\Models\AppropriationOrdinance::class)
            <a href="{{ route('appropriation-ordinances.create') }}" class="splis-btn-primary">Add appropriation ordinance</a>
        @endcan
    </div>

    <form method="GET" class="splis-filter-panel mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="splis-label" for="q">Search</label>
                <input type="text" name="q" id="q" value="{{ request('q') }}" class="splis-input" placeholder="Number or title">
            </div>
            <div>
                <label class="splis-label" for="series">Series year</label>
                <select name="series" id="series" class="splis-select">
                    <option value="">All years</option>
                    @foreach ($seriesYears as $year)
                        <option value="{{ $year }}" @selected((string) request('series') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="splis-btn-primary">Search</button>
                <a href="{{ route('appropriation-ordinances.index') }}" class="splis-btn-ghost">Clear</a>
            </div>
        </div>
    </form>

    <div class="splis-table-wrap splis-card overflow-hidden">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Appro. Ord. No.</th>
                    <th>Title</th>
                    <th>Date received</th>
                    <th>Date passed by SP</th>
                    <th>Date approved by Governor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="whitespace-nowrap">
                            <a href="{{ route('appropriation-ordinances.show', $record) }}" class="splis-link">{{ $record->displayNumber() }} ({{ $record->series_year }})</a>
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($record->subject, 100) }}</td>
                        <td class="whitespace-nowrap">{{ $record->date_received?->format('M j, Y') ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ $record->date_passed?->format('M j, Y') ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ $record->date_approved?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-sm text-slate-500">No appropriation ordinances found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($records->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $records->links() }}</div>
        @endif
    </div>
</div>
@endsection
