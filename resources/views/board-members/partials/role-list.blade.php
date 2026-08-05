@props(['title', 'memberships', 'empty' => 'None'])

<section>
    <h3 class="splis-label mb-2">{{ $title }}</h3>
    @if ($memberships->isNotEmpty())
        <ul class="space-y-2">
            @foreach ($memberships as $membership)
                <li class="rounded-lg border border-slate-200 bg-white px-4 py-3 dark:border-slate-600 dark:bg-slate-900/40">
                    @if ($membership->committee)
                        <a
                            href="{{ route('committees.show', ['committee' => $membership->committee, 'term' => $membership->committee_term_id]) }}"
                            class="inline-flex max-w-full hover:opacity-90"
                        >
                            <x-committee-meta :committee="$membership->committee" class="splis-list-committee--lg !normal-case tracking-normal" />
                        </a>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-slate-500">{{ $empty }}</p>
    @endif
</section>
