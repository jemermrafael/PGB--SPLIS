@extends('layouts.app')

@section('title', 'My Watchlist — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <x-page-header
        class="!mb-6"
        title="My Watchlist"
        subtitle="Items you are watching for publication updates."
    />

    <div class="splis-card overflow-hidden">
        <div class="splis-card-header splis-card-header--emphasis">
            <h2 class="splis-card-title">Watched items</h2>
        </div>
        <div class="splis-card-body p-0">
            @if ($items->isEmpty())
                <p class="p-4 text-sm text-slate-500">You have no watched items yet.</p>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($items as $item)
                        @php
                            $watchable = $item->watchable;
                            $label = $watchable instanceof \App\Models\AgendaItem
                                ? 'Agenda '.$watchable->displayLabel()
                                : ($watchable instanceof \App\Models\Resolution
                                    ? 'Resolution '.$watchable->resolution_no
                                    : ($watchable instanceof \App\Models\Ordinance
                                        ? $watchable->displayNumber()
                                        : 'Unknown item'));
                            $url = $watchable instanceof \App\Models\AgendaItem
                                ? route('agenda.show', $watchable)
                                : ($watchable instanceof \App\Models\Resolution
                                    ? route('resolutions.show', $watchable)
                                    : ($watchable instanceof \App\Models\Ordinance
                                        ? route('ordinances.show', $watchable)
                                        : null));
                        @endphp
                        <li class="p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $label }}</p>
                                    <p class="text-xs text-slate-500">Watching since {{ $item->created_at?->format('M d, Y') }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if ($url)
                                        <a href="{{ $url }}" class="splis-btn-secondary text-sm">Open</a>
                                    @endif
                                    <form method="POST" action="{{ route('board-member.watchlist.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="splis-btn-danger text-sm">Unwatch</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
