@extends('layouts.app')

@section('title', 'Resolutions — '.config('app.name'))

@section('content')
<div
    class="max-w-6xl"
    id="bm-my-resolutions"
    data-search-url="{{ route('board-member.resolutions.search') }}"
>
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Resolutions</h1>
            <p class="splis-page-subtitle">Resolutions connected to agendas from committees you chair.</p>
        </div>
        <a href="{{ route('board-member.agenda.index') }}" class="splis-btn-ghost whitespace-nowrap">My Agenda</a>
    </div>

    @if ($unlinked)
        <div class="splis-alert-error mb-6">This account is not linked to a Board Member profile yet.</div>
    @endif

    <form method="GET" id="bm-resolutions-search-form" class="splis-filter-panel mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="splis-label" for="bm-resolutions-q">Search</label>
                <input type="text" name="q" id="bm-resolutions-q" class="splis-input" placeholder="Number or title" autocomplete="off">
            </div>
            <div>
                <label class="splis-label" for="bm-resolutions-series">Series year</label>
                <select name="series" id="bm-resolutions-series" class="splis-select">
                    <option value="">All years</option>
                    @foreach ($seriesYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="splis-btn-primary">Search</button>
                <button type="reset" class="splis-btn-ghost">Clear</button>
            </div>
        </div>
    </form>

    <p id="bm-resolutions-meta" class="mb-3 text-sm text-slate-500 dark:text-slate-400">Loading resolutions…</p>
    <div id="bm-resolutions-results">
        <div id="bm-resolutions-table" class="splis-table-wrap splis-card overflow-hidden">
            <table class="splis-table">
                <thead>
                    <tr>
                        <th class="w-12">PDF</th>
                        <th>Number</th>
                        <th>Title</th>
                        <th class="hidden md:table-cell">Agenda</th>
                        <th class="hidden lg:table-cell">Committee</th>
                        <th class="hidden lg:table-cell">Date Approved</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-sm text-slate-500">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="bm-resolutions-pagination" class="mt-6"></div>
</div>
@endsection
