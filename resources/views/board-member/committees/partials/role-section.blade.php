@props(['title', 'assignments', 'empty' => 'None', 'badge' => '', 'selectedTerm' => null])

<section class="splis-card">
    <div class="splis-card-header">
        <h2 class="splis-card-title">{{ $title }}</h2>
        <span class="splis-accordion-count">{{ $assignments->count() }}</span>
    </div>
    <div class="splis-card-body space-y-3">
        @forelse ($assignments as $assignment)
            @php
                $showUrl = route('board-member.committees.show', array_filter([
                    'committee' => $assignment['committee'],
                    'term' => $selectedTerm?->id,
                ]));
            @endphp
            <div class="flex items-start justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-600 dark:bg-slate-900/40">
                <div class="min-w-0">
                    <a href="{{ $showUrl }}" class="splis-link font-medium">
                        {{ $assignment['committee']->name }}
                    </a>
                    @if ($badge)
                        <p class="mt-1"><span class="splis-badge splis-badge--muted">{{ $badge }}</span></p>
                    @endif
                </div>
                <a href="{{ $showUrl }}" class="shrink-0 text-sm text-brand-700 hover:underline dark:text-brand-300">
                    View
                </a>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ $empty }}</p>
        @endforelse
    </div>
</section>
