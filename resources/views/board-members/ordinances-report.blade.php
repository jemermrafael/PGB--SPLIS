@extends('layouts.app')

@section('title', 'Board Member Ordinances — '.config('app.name'))

@section('content')
<div
    class="max-w-6xl"
    id="bm-authored-ordinances"
    data-search-url="{{ route('admin.board-member-ordinances.search') }}"
    data-initial-member-id="{{ $selectedMemberId }}"
>
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">Board Member Authored Ordinances</h1>
            <p class="splis-page-subtitle">Provincial Ordinances by Board Member — passed or pending.</p>
        </div>
    </div>

    <form method="GET" id="bm-authored-ordinances-form" class="splis-card splis-card-body mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-[16rem] flex-1">
                <label class="splis-label" for="board_member_id">Board Member</label>
                <select name="board_member_id" id="board_member_id" class="splis-select">
                    <option value="">Select Board Member</option>
                    @foreach ($boardMembers as $member)
                        <option value="{{ $member->id }}" @selected($selectedMemberId === $member->id)>{{ $member->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[16rem] flex-1">
                <label class="splis-label" for="q">Search ordinances</label>
                <input
                    type="text"
                    name="q"
                    id="q"
                    value="{{ request('q') }}"
                    class="splis-input"
                    placeholder="Number or title"
                    autocomplete="off"
                >
            </div>
            <div class="min-w-[14rem]">
                <label class="splis-label" for="role">Authorship</label>
                <select name="role" id="role" class="splis-select">
                    <option value="">All roles</option>
                    <option value="authored_sponsored">Authored and Sponsored</option>
                    <option value="author">Authored</option>
                    <option value="sponsor">Sponsored</option>
                </select>
            </div>
            <button type="submit" class="splis-btn-primary">Search</button>
        </div>
    </form>

    <p id="bm-authored-ordinances-hint" class="mb-4 text-sm text-slate-500 dark:text-slate-400">
        Select a Board Member to view authored ordinances.
    </p>

    <div id="bm-authored-ordinances-results" class="splis-card overflow-hidden" hidden>
        <div class="splis-card-header">
            <h2 id="bm-authored-ordinances-member-name" class="splis-card-title"></h2>
            <p id="bm-authored-ordinances-meta" class="splis-card-subtitle"></p>
        </div>
        <div id="bm-authored-ordinances-table" class="splis-table-wrap">
            <table class="splis-table">
                <thead>
                    <tr>
                        <th class="w-12 text-center" title="PDF">
                            <span class="sr-only">PDF</span>
                            <svg class="mx-auto h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </th>
                        <th>Number</th>
                        <th>Subject</th>
                        <th>Date enacted</th>
                        <th>Date approved</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div id="bm-authored-ordinances-pagination" class="border-t border-slate-200 px-4 py-3 dark:border-slate-700"></div>
    </div>
</div>
@endsection
