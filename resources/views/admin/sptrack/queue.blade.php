@extends('layouts.app')

@section('title', 'SP Track Queue — '.config('app.name'))

@section('content')
<div class="splis-page-header">
    <div>
        <h1 class="splis-page-title">Migration queue</h1>
        <p class="splis-page-subtitle">Review sptrack rows and choose enrich, create new, or skip.</p>
    </div>
    <a href="{{ route('admin.sptrack.index') }}" class="splis-btn-secondary">Back to migration</a>
</div>

<div class="mb-6 flex flex-wrap gap-2">
    @foreach (['high' => 'High confidence', 'review' => 'Needs review', 'create' => 'Create new', 'skip' => 'Skip', 'approved' => 'Approved', 'applied' => 'Applied'] as $key => $label)
        <a
            href="{{ route('admin.sptrack.queue', ['tab' => $key]) }}"
            @class(['splis-btn-secondary', 'ring-2 ring-[#12325a]' => $tab === $key])
        >{{ $label }}</a>
    @endforeach
</div>

<form method="GET" class="splis-filter-panel mb-6">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label class="splis-label">Search</label>
            <input type="text" name="q" value="{{ request('q') }}" class="splis-input" placeholder="Title, number, municipality">
        </div>
        <div>
            <label class="splis-label">Series</label>
            <input type="number" name="series" value="{{ request('series') }}" class="splis-input" placeholder="Year">
        </div>
        <div class="flex items-end">
            <button type="submit" class="splis-btn-primary">Filter</button>
        </div>
    </div>
</form>

<form id="bulk-form" method="POST" action="{{ route('admin.sptrack.bulk') }}" class="mb-4 flex flex-wrap gap-2">
    @csrf
    <input type="hidden" name="action" value="approve">
    <div id="bulk-ids"></div>
    <button type="submit" class="splis-btn-secondary text-sm">Approve selected</button>
</form>

<div class="splis-table-wrap overflow-x-auto">
    <table class="splis-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="check-all" aria-label="Select all rows"></th>
                <th>SP Track</th>
                <th>Suggested SPLIS match</th>
                <th>Confidence</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr class="align-top">
                    <td>
                        @if ($item->queue_status === 'pending')
                            <input type="checkbox" name="ids[]" value="{{ $item->id }}" class="row-check" aria-label="Select row {{ $item->legacy_file_id }}">
                        @endif
                    </td>
                    <td class="max-w-md">
                        <p class="splis-queue-heading">
                            FileId {{ $item->legacy_file_id }} · {{ $item->sp_res_no ?? '—' }} / {{ $item->sp_series ?? '—' }}
                        </p>
                        <p class="splis-queue-meta line-clamp-3">{{ $item->sp_title ?: $item->mun_title }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $item->municipality }} · {{ $item->sp_date_approved?->format('M j, Y') ?? 'No date' }}
                        </p>
                    </td>
                    <td class="max-w-md">
                        @php $match = $item->userResolution ?? $item->suggestedResolution; @endphp
                        @if ($match)
                            <p class="splis-queue-heading">{{ $match->resolution_no }} ({{ $match->series }})</p>
                            <p class="splis-queue-meta line-clamp-2">{{ $match->resolution_title }}</p>
                        @else
                            <p class="splis-queue-meta">No match — proposed: {{ $item->proposed_action }}</p>
                        @endif
                        @if ($item->match_signals)
                            <p class="mt-1 text-xs text-slate-500">
                                @if (isset($item->match_signals['title_similarity']))
                                    Title {{ $item->match_signals['title_similarity'] }}%
                                @endif
                                @if (! empty($item->match_signals['date_match']))
                                    · Date match
                                @endif
                            </p>
                        @endif
                    </td>
                    <td>
                        <span class="splis-badge-legacy">{{ $item->confidence }}</span>
                        <p class="mt-1 text-xs text-slate-500">{{ $item->queue_status }}</p>
                    </td>
                    <td class="min-w-[16rem]">
                        @if ($item->queue_status === 'pending')
                            <form method="POST" action="{{ route('admin.sptrack.update', $item) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <select name="user_action" class="splis-select w-full text-sm">
                                    <option value="enrich" @selected(($item->user_action ?? $item->proposed_action) === 'enrich')>Enrich existing</option>
                                    <option value="create" @selected(($item->user_action ?? $item->proposed_action) === 'create')>Create new</option>
                                    <option value="skip" @selected(($item->user_action ?? $item->proposed_action) === 'skip')>Skip</option>
                                </select>
                                <input
                                    type="number"
                                    name="user_resolution_id"
                                    value="{{ $item->user_resolution_id ?? $item->suggested_resolution_id }}"
                                    class="splis-input w-full text-sm"
                                    placeholder="SPLIS resolution ID"
                                >
                                <label class="flex items-center gap-2 text-xs text-slate-600">
                                    <input type="checkbox" name="approve" value="1">
                                    Approve now
                                </label>
                                <button type="submit" class="splis-btn-secondary w-full text-sm">Save</button>
                            </form>
                        @else
                            <p class="text-xs text-slate-500">{{ $item->effectiveAction() }}</p>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-8 text-center text-slate-500">No queue rows in this tab.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Uses partials.splis-pagination (also the app-wide default via Paginator::defaultView) --}}
<div class="mt-6">
    {{ $items->links() }}
</div>

@push('scripts')
<script>
    document.getElementById('check-all')?.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    });

    document.getElementById('bulk-form')?.addEventListener('submit', function (e) {
        const container = document.getElementById('bulk-ids');
        container.innerHTML = '';
        document.querySelectorAll('.row-check:checked').forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            container.appendChild(input);
        });
        if (container.children.length === 0) {
            e.preventDefault();
            alert('Select at least one row.');
        }
    });
</script>
@endpush
@endsection
