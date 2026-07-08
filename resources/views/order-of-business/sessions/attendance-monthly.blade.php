@extends('layouts.app')

@section('title', 'Monthly attendance — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Monthly attendance report</h1>
            <p class="splis-page-subtitle">{{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }} · {{ $sessions->count() }} session(s)</p>
        </div>
        <a href="{{ route('ob.sessions.index') }}" class="splis-btn-secondary">Back to sessions</a>
    </div>

    <form method="GET" class="splis-card splis-card-body mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label class="splis-label" for="month">Month</label>
            <select name="month" id="month" class="splis-select">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($month === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="splis-label" for="year">Year</label>
            <input type="number" name="year" id="year" value="{{ $year }}" min="2020" max="2100" class="splis-input w-28">
        </div>
        <button type="submit" class="splis-btn-primary">View report</button>
    </form>

    <div class="splis-table-wrap splis-card overflow-hidden">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Board member</th>
                    <th>District</th>
                    <th>Present</th>
                    <th>Sessions</th>
                    <th>Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary as $row)
                    @php
                        $member = $row['member'];
                        $district = $member->districtForTerm(null) ?? $member->district ?? '—';
                        $rate = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100) : 0;
                    @endphp
                    <tr>
                        <td>{{ $member->displayName() }}</td>
                        <td>{{ $district }}</td>
                        <td>{{ $row['present'] }}</td>
                        <td>{{ $row['total'] }}</td>
                        <td>{{ $rate }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
