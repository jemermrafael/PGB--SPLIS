@php
    /** @var \App\Models\BoardMember|null $boardMember */
    /** @var \App\Models\CommitteeTerm $term */
    $fallback = $fallback ?? null;
@endphp

@if ($boardMember)
    <a
        href="{{ route('board-members.show', ['boardMember' => $boardMember, 'term' => $term->id]) }}"
        class="splis-link"
    >{{ $boardMember->displayName() }}</a>
@elseif ($fallback)
    {{ $fallback }}
@else
    —
@endif
