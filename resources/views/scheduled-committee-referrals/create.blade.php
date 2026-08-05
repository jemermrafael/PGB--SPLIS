@extends('layouts.app')

@section('title', 'Schedule Committee Referral — '.config('app.name'))

@section('content')
<div class="max-w-4xl">
    <div class="splis-page-header">
        <x-page-heading
            title="Schedule Committee Referral"
            subtitle="Choose a Session with Regular Unassigned Business, then set when Chairs should receive the Referral."
            icon="meeting"
            page="scheduled_committee_referrals"
        />
        <a href="{{ route('scheduled-committee-referrals.index') }}" class="splis-btn-secondary whitespace-nowrap">Back to list</a>
    </div>

    <form method="GET" action="{{ route('scheduled-committee-referrals.create') }}" class="splis-card mb-6 p-4 sm:p-5">
        <label for="legislative_session_id_preview" class="splis-label">Order of Business session</label>
        <div class="mt-1 flex flex-wrap gap-2">
            <select
                id="legislative_session_id_preview"
                name="legislative_session_id"
                class="splis-input max-w-xl"
                onchange="this.form.submit()"
            >
                <option value="">Select a session…</option>
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}" @selected(($selectedSession?->id ?? null) === $session->id)>
                        {{ $session->displayTitle() }} — {{ $session->session_date?->format('M j, Y') }}
                    </option>
                @endforeach
            </select>
        </div>
        <p class="mt-2 text-xs text-slate-500">Only agendas under <strong>2. REGULAR UNASSIGNED BUSINESS</strong> are included. Each item is sent only to that Committee’s Chair.</p>
    </form>

    @if ($selectedSession)
        <div class="splis-card mb-6 overflow-hidden">
            <div class="border-slate-200 px-4 py-3 dark:border-slate-700 sm:px-5">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Preview — Regular Unassigned Business</h2>
                <p class="text-xs text-slate-500">{{ $selectedSession->displayTitle() }}</p>
            </div>
            <div class="splis-table-wrap" data-drag-scroll>
                <table class="splis-table">
                    <thead>
                        <tr>
                            <th>Agenda</th>
                            <th>Committee</th>
                            <th>Chair (recipient)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($preview as $row)
                            <tr>
                                <td>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $row['agenda']->displayLabel() }}</div>
                                    <div class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($row['agenda']->title ?: 'Untitled', 90) }}</div>
                                </td>
                                <td class="text-sm">
                                    {{ $row['committee']?->name ?? '—' }}
                                </td>
                                <td class="text-sm">
                                    @if ($row['chair'])
                                        {{ $row['chair']->displayName() }}
                                        @if (! $row['chair_user'])
                                            <span class="block text-xs text-amber-700 dark:text-amber-300">No linked BM account — will not notify</span>
                                        @endif
                                    @else
                                        <span class="text-amber-700 dark:text-amber-300">No chair assigned</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-sm text-slate-500">
                                    No Regular Unassigned Business agendas on this session’s Order of Business.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($preview->isNotEmpty())
            <form method="POST" action="{{ route('scheduled-committee-referrals.store') }}" class="splis-card p-4 sm:p-5">
                @csrf
                <input type="hidden" name="legislative_session_id" value="{{ $selectedSession->id }}">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="scheduled_at" class="splis-label">Send date &amp; time</label>
                        <input
                            id="scheduled_at"
                            type="datetime-local"
                            name="scheduled_at"
                            class="splis-input mt-1"
                            value="{{ old('scheduled_at', now()->addHour()->format('Y-m-d\TH:i')) }}"
                            required
                        >
                        @error('scheduled_at')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-slate-500">Usually after the OB session ends. If the time is now or past, referrals send immediately.</p>
                    </div>
                    <div>
                        <label for="notes" class="splis-label">Notes <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea id="notes" name="notes" rows="3" class="splis-input mt-1" maxlength="2000">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/40">
                    <label class="flex items-start gap-3 text-sm text-slate-800 dark:text-slate-100">
                        <input
                            type="checkbox"
                            name="send_email"
                            value="1"
                            class="mt-0.5"
                            @checked(old('send_email'))
                        >
                        <span>
                            <span class="font-medium">Also send email to Committee Chairs</span>
                            <span class="mt-0.5 block text-xs font-normal text-slate-500">
                                In-app alerts always go to Chairs with linked BM accounts. Emails use the
                                <strong>Scheduled Committee Referral</strong> template under Email Notifications,
                                and each BM can opt in or out on My Profile.
                            </span>
                        </span>
                    </label>
                </div>

                @error('legislative_session_id')
                    <p class="mt-3 text-sm text-rose-600">{{ $message }}</p>
                @enderror

                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="splis-btn-primary">Schedule Referral to Chairs</button>
                    <a href="{{ route('scheduled-committee-referrals.index') }}" class="splis-btn-ghost">Cancel</a>
                </div>
            </form>
        @endif
    @endif
</div>
@endsection
