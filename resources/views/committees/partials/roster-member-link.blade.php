@php
    /** @var \App\Models\BoardMember|null $boardMember */
    /** @var \App\Models\CommitteeTerm $term */
    $fallback = $fallback ?? null;
@endphp

@if ($boardMember)
    <a
        href="{{ route('board-members.show', ['boardMember' => $boardMember, 'term' => $term->id]) }}"
        class="inline-flex min-w-0 items-center gap-3 text-slate-900 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300"
    >
        @if ($boardMember->photo_path)
            <span class="splis-bm-photo-avatar" aria-hidden="true">
                <img
                    src="{{ route('board-members.photo', $boardMember) }}"
                    alt=""
                    loading="lazy"
                >
            </span>
        @else
            <span class="splis-bm-photo-avatar splis-bm-photo-avatar--empty" aria-hidden="true">
                <x-icon name="user" class="h-4 w-4" />
            </span>
        @endif
        <span class="min-w-0 font-medium">{{ $boardMember->displayName() }}</span>
    </a>
@elseif ($fallback)
    <span class="inline-flex min-w-0 items-center gap-3">
        <span class="splis-bm-photo-avatar splis-bm-photo-avatar--empty" aria-hidden="true">
            <x-icon name="user" class="h-4 w-4" />
        </span>
        <span class="min-w-0">{{ $fallback }}</span>
    </span>
@else
    —
@endif
