@php
    /** @var \App\Models\BoardMemberCommitteeReport|null $report */
    $report = $report ?? null;
    $isEdit = $report !== null;
    $selectedAgendaIds = collect($selectedAgendaIds ?? [])->map(fn ($id) => (int) $id)->all();
    $chairMembers = $chairMembers ?? collect();
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('committee-reports.update', $report) : route('committee-reports.store') }}"
    enctype="multipart/form-data"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="splis-card splis-card-body space-y-5">
            @if (! $isEdit)
                <div>
                    <label class="splis-label" for="board_member_id">Board Member (Chair)</label>
                    <select name="board_member_id" id="board_member_id" class="splis-select" required>
                        <option value="">Select Board Member</option>
                        @foreach ($chairMembers as $member)
                            <option value="{{ $member->id }}" @selected((int) old('board_member_id', $boardMemberId) === (int) $member->id)>
                                {{ $member->displayName() }}
                            </option>
                        @endforeach
                    </select>
                    @error('board_member_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="board_member_id" id="board_member_id" value="{{ $boardMemberId }}">
            @endif

            <div>
                <label class="splis-label" for="title">Report title (optional)</label>
                <input
                    type="text"
                    name="title"
                    id="title"
                    value="{{ old('title', $report?->title) }}"
                    class="splis-input"
                    placeholder="Committee Report Title"
                >
            </div>

            <div>
                <label class="splis-label" for="pdf">
                    {{ $isEdit ? 'Replace PDF (optional)' : 'PDF file' }}
                </label>
                <input
                    type="file"
                    name="pdf"
                    id="pdf"
                    accept="application/pdf"
                    @required(! $isEdit)
                    class="splis-input"
                >
                @error('pdf')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @if ($isEdit && $report->original_filename)
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Current file: <span class="font-medium">{{ $report->original_filename }}</span>
                    </p>
                @endif
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">
                Tagged agendas will use this PDF as their Committee Report and are placed automatically under
                <strong>IV. Committee Reports</strong> on the nearest upcoming Order of Business.
            </p>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="splis-btn-primary">
                    {{ $isEdit ? 'Save Changes' : 'Submit Report' }}
                </button>
                <a href="{{ route('committee-reports.index') }}" class="splis-btn-ghost">Cancel</a>
            </div>
        </div>

        <div
            id="bm-cr-agenda-panel"
            class="splis-card overflow-hidden"
            data-search-url="{{ $agendaSearchUrl }}"
            data-board-member-param="1"
        >
            <div class="splis-card-header">
                <div>
                    <h2 class="splis-card-title">Chairmanship Agenda</h2>
                    <p class="splis-card-subtitle">Items referred to committees this Board Member chairs (without a report yet).</p>
                </div>
            </div>
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="splis-label" for="bm-cr-committee-id">Committee</label>
                        <select id="bm-cr-committee-id" class="splis-select" @disabled(! $boardMemberId && ! $isEdit)>
                            <option value="">All Chairmanships</option>
                            @foreach ($chairCommittees as $committee)
                                <option value="{{ $committee->id }}" @selected((int) $committeeId === (int) $committee->id)>{{ $committee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="splis-label" for="bm-cr-q">Search</label>
                        <input
                            type="search"
                            id="bm-cr-q"
                            value="{{ $q }}"
                            class="splis-input"
                            placeholder="Tracking no. or title"
                            autocomplete="off"
                            @disabled(! $boardMemberId && ! $isEdit)
                        >
                    </div>
                </div>
            </div>
            <div id="bm-cr-agenda-list" class="max-h-[28rem] space-y-1 overflow-y-auto p-3">
                @forelse ($agendaItems as $agenda)
                    @php
                        $titleFull = trim((string) ($agenda->title ?: 'Untitled'));
                        $titleWords = $titleFull === '' ? [] : (preg_split('/\s+/', $titleFull) ?: []);
                        $titleTruncated = count($titleWords) > 20;
                        $titleDisplay = $titleTruncated
                            ? implode(' ', array_slice($titleWords, 0, 20)).'…'
                            : $titleFull;
                    @endphp
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800/60">
                        <input
                            type="checkbox"
                            name="agenda_item_ids[]"
                            value="{{ $agenda->id }}"
                            @checked(in_array((int) $agenda->id, $selectedAgendaIds, true))
                            class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                        >
                        <span class="min-w-0 flex-1">
                            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $agenda->listNumberLabel() }}</span>
                            <span class="mt-0.5 block text-slate-600 dark:text-slate-300">
                                @if ($titleTruncated)
                                    <span class="splis-title-tip" data-full-title="{{ e($titleFull) }}" tabindex="0">{{ $titleDisplay }}</span>
                                @else
                                    {{ $titleDisplay }}
                                @endif
                            </span>
                            @if ($agenda->committee_referred)
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $agenda->committee_referred }}</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <p class="px-2 py-8 text-center text-sm text-slate-500">
                        @if (! $boardMemberId && ! $isEdit)
                            Select a Board Member chair to load open agendas.
                        @elseif ($q !== '' || $committeeId)
                            No chairmanship agenda items matched your filter.
                        @else
                            No open chairmanship agenda items need a committee report.
                        @endif
                    </p>
                @endforelse
            </div>
            @error('agenda_item_ids')
                <p class="border-t border-slate-200 px-4 py-2 text-sm text-red-600 dark:border-slate-700">{{ $message }}</p>
            @enderror
        </div>
    </div>
</form>
