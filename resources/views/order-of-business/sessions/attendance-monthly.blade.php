@extends('layouts.app')

@section('title', 'Monthly attendance — '.config('app.name'))

@section('content')
<div class="max-w-full">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Monthly Attendance Report</h1>
            <p class="splis-page-subtitle">{{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }} · {{ $sessions->count() }} session(s)</p>
        </div>
        <a href="{{ route('ob.sessions.index') }}" class="splis-btn-secondary">Back to Sessions</a>
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
        <button type="submit" class="splis-btn-primary">View Report</button>
    </form>

    @if ($sessions->isEmpty())
        <div class="splis-card splis-card-body">
            <p class="text-sm text-slate-600 dark:text-slate-400">No sessions recorded for this month.</p>
        </div>
    @else
        <div class="splis-table-wrap splis-card overflow-x-auto">
            <table class="splis-table splis-attendance-monthly-table min-w-max">
                <thead>
                    <tr>
                        <th class="splis-attendance-monthly-sticky splis-attendance-monthly-sticky--member">Board member</th>
                        <th class="splis-attendance-monthly-sticky splis-attendance-monthly-sticky--district">District</th>
                        @foreach ($sessions as $session)
                            <th class="splis-attendance-monthly-session whitespace-nowrap text-center">
                                <span class="block text-xs font-semibold">{{ $session->session_date?->format('M j') }}</span>
                                @if ($session->session_number)
                                    <span class="mt-0.5 block text-[10px] font-normal text-slate-500 dark:text-slate-400">{{ $session->session_number }}</span>
                                @endif
                            </th>
                        @endforeach
                        <th class="whitespace-nowrap text-center">Present</th>
                        <th class="whitespace-nowrap text-center">Sessions</th>
                        <th class="whitespace-nowrap text-center">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary as $row)
                        @php
                            $member = $row['member'];
                            $district = $member->districtForTerm($currentTermId ?? null) ?? $member->district ?? '—';
                            $rate = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100) : 0;
                        @endphp
                        <tr>
                            <td class="splis-attendance-monthly-sticky splis-attendance-monthly-sticky--member whitespace-nowrap font-medium">{{ $member->displayName() }}</td>
                            <td class="splis-attendance-monthly-sticky splis-attendance-monthly-sticky--district whitespace-nowrap">{{ $district }}</td>
                            @foreach ($sessions as $session)
                                @php
                                    $presence = $row['sessions'][$session->id] ?? null;
                                @endphp
                                <td class="text-center">
                                    @if ($presence === true)
                                        <span class="splis-attendance-mark splis-attendance-mark--present" title="Present">✓</span>
                                    @elseif ($presence === false)
                                        <span class="splis-attendance-mark splis-attendance-mark--absent" title="Absent">—</span>
                                    @else
                                        <span class="splis-attendance-mark splis-attendance-mark--unset" title="Not recorded">·</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center font-semibold tabular-nums">{{ $row['present'] }}</td>
                            <td class="text-center tabular-nums">{{ $row['total'] }}</td>
                            <td class="text-center tabular-nums">{{ $rate }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            ✓ Present · — Absent · · Not recorded
        </p>
    @endif
</div>
@endsection
