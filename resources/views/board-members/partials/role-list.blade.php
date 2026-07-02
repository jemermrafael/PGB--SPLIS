@props(['title', 'memberships', 'empty' => 'None'])

<section>
    <h3 class="splis-label mb-2">{{ $title }}</h3>
    @if ($memberships->isNotEmpty())
        <ul class="space-y-2">
            @foreach ($memberships as $membership)
                <li class="flex items-start justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-600 dark:bg-slate-900/40">
                    <span class="text-slate-900 dark:text-slate-100">{{ $membership->committee?->name }}</span>
                    <a href="{{ route('committees.show', ['committee' => $membership->committee, 'term' => $membership->committee_term_id]) }}" class="shrink-0 text-sm text-brand-700 hover:underline dark:text-brand-300">View committee</a>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-slate-500">{{ $empty }}</p>
    @endif
</section>
