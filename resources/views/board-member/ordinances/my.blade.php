@extends('layouts.app')

@section('title', 'My Ordinances — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">My Ordinances</h1>
            <p class="splis-page-subtitle">Provincial ordinances you authored or sponsored.</p>
        </div>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a board member profile yet.</div>
    @endif

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
                <a href="{{ route('board-member.ordinances.index') }}" class="splis-btn-ghost">Clear</a>
            </div>
        </div>
    </form>

    @include('board-member.ordinances.partials.table', [
        'records' => $records,
        'showType' => false,
        'emptyMessage' => 'No authored or sponsored ordinances found.',
    ])
</div>
@endsection
