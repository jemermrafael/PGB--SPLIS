@extends('layouts.app')

@section('title', 'Committee Reports — '.config('app.name'))

@section('content')
<div
    id="staff-committee-reports"
    class="max-w-6xl"
    data-search-url="{{ $searchUrl }}"
>
    <div class="splis-page-header">
        <x-page-heading
            title="Committee Reports"
            subtitle="Reports submitted by Board Members and staff. BM-submitted reports are view-only for encoders and admins."
            icon="file-text"
        />
        @can('create', App\Models\BoardMemberCommitteeReport::class)
            <a href="{{ route('committee-reports.create') }}" class="splis-btn-primary inline-flex items-center gap-2">
                <x-icon name="plus" class="h-4 w-4" />
                Submit Report
            </a>
        @endcan
    </div>

    <form id="staff-cr-search-form" class="splis-filter-panel mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="splis-label" for="staff-cr-q">Search</label>
                <input type="search" name="q" id="staff-cr-q" class="splis-input" placeholder="Title, file, or board member" autocomplete="off">
            </div>
            <div>
                <label class="splis-label" for="staff-cr-committee">Committee</label>
                <select name="committee_id" id="staff-cr-committee" class="splis-select">
                    <option value="">All Committees</option>
                    @foreach ($committees as $committee)
                        <option value="{{ $committee->id }}">{{ $committee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="splis-label" for="staff-cr-date-from">Submitted from</label>
                <input type="date" name="date_from" id="staff-cr-date-from" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="staff-cr-date-to">Submitted to</label>
                <input type="date" name="date_to" id="staff-cr-date-to" class="splis-input">
            </div>
        </div>
        <div class="mt-4 flex gap-2">
            <button type="submit" class="splis-btn-primary">Search</button>
            <button type="reset" class="splis-btn-ghost">Clear</button>
        </div>
    </form>

    <p id="staff-cr-meta" class="mb-3 text-sm text-slate-500 dark:text-slate-400">Loading committee reports…</p>

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th class="whitespace-nowrap">Submitted</th>
                    <th class="whitespace-nowrap">Board Member</th>
                    <th>Title / File</th>
                    <th>Agenda tags</th>
                    <th class="hidden lg:table-cell whitespace-nowrap">Submitted by</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="staff-cr-body">
                <tr>
                    <td colspan="6" class="py-10 text-center text-slate-500">Loading…</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="staff-cr-pagination" class="mt-6"></div>
</div>
@endsection
