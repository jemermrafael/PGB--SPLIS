@extends('layouts.app')

@section('title', 'Schedule Committee Referral — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <x-page-heading
            title="Schedule Committee Referral"
            subtitle="Send Regular Unassigned Business Agendas from an Order of Business to Committee Chairs at a chosen date and time."
            icon="meeting"
            page="scheduled-committee-referrals"
        />
        <a href="{{ route('scheduled-committee-referrals.create') }}" class="splis-btn-primary inline-flex items-center gap-2 whitespace-nowrap">
            <x-icon name="plus" class="h-4 w-4" stroke-width="2" />
            Schedule Referral
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="splis-table-wrap" data-drag-scroll>
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Scheduled</th>
                    <th>Status</th>
                    <th class="hidden sm:table-cell">Email</th>
                    <th class="hidden md:table-cell">Deliveries</th>
                    <th class="hidden lg:table-cell">Created by</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schedules as $schedule)
                    @php
                        $session = $schedule->legislativeSession;
                        $status = $schedule->status;
                    @endphp
                    <tr>
                        <td>
                            @if ($session)
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ $session->displayTitle() }}</div>
                            @else
                                <span class="text-slate-400">Session removed</span>
                            @endif
                            @if ($schedule->notes)
                                <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($schedule->notes, 80) }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-sm">
                            {{ $schedule->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                            @if ($schedule->sent_at)
                                <div class="text-xs text-slate-500">Sent {{ $schedule->sent_at->timezone(config('app.timezone'))->format('M j, g:i A') }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($status === \App\Models\ScheduledCommitteeReferral::STATUS_PENDING)
                                <span class="splis-badge splis-badge--muted">Pending</span>
                            @elseif ($status === \App\Models\ScheduledCommitteeReferral::STATUS_SENT)
                                <span class="splis-badge">Sent</span>
                            @else
                                <span class="splis-badge splis-badge--muted">Cancelled</span>
                            @endif
                        </td>
                        <td class="hidden sm:table-cell text-sm text-slate-600 dark:text-slate-300">
                            {{ $schedule->send_email ? 'Yes' : 'No' }}
                        </td>
                        <td class="hidden md:table-cell text-sm text-slate-600 dark:text-slate-300">
                            {{ number_format($schedule->deliveries->count()) }}
                        </td>
                        <td class="hidden lg:table-cell text-sm text-slate-600 dark:text-slate-300">
                            {{ $schedule->creator?->name ?? '—' }}
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($schedule->isPending())
                                    <form method="POST" action="{{ route('scheduled-committee-referrals.cancel', $schedule) }}" class="inline" onsubmit="return confirm('Cancel this scheduled referral?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="splis-btn-ghost !py-1.5 text-sm text-rose-700 dark:text-rose-300">Cancel</button>
                                    </form>
                                @endif
                                @if (auth()->user()?->canAdmin())
                                    <form
                                        method="POST"
                                        action="{{ route('scheduled-committee-referrals.destroy', $schedule) }}"
                                        class="inline"
                                        data-confirm-submit
                                        data-confirm-title="Move scheduled referral to trash?"
                                        data-confirm-message="Move this Scheduled Committee Referral to trash? Superadmin can restore from Trash."
                                        data-confirm-label="Delete"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="splis-btn-ghost !py-1.5 text-sm text-rose-700 dark:text-rose-300">Delete</button>
                                    </form>
                                @elseif (! $schedule->isPending())
                                    <span class="text-slate-400">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-sm text-slate-500">
                            No scheduled committee referrals yet.
                            <a href="{{ route('scheduled-committee-referrals.create') }}" class="splis-link font-medium">Schedule one</a>
                            from Regular Unassigned Business on an OB.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $schedules->links() }}
    </div>
</div>
@endsection
