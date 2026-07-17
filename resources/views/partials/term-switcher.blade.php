@php
    $showCurrentBadge = $showCurrentBadge ?? true;
    $routeParams = $routeParams ?? [];
    $class = $class ?? 'mb-6';
@endphp

<nav class="splis-term-switcher {{ $class }}" aria-label="Election terms" data-term-switcher>
    <button
        type="button"
        class="splis-term-switcher-arrow"
        data-term-switcher-prev
        hidden
        aria-label="Show newer terms"
    >
        <x-icon name="arrow-left" class="h-4 w-4" />
    </button>

    <div class="splis-term-switcher-track" data-term-switcher-track data-drag-scroll>
        @foreach ($terms as $term)
            <a
                href="{{ route($routeName, array_merge($routeParams, ['term' => $term->id])) }}"
                class="{{ $term->id === $selectedTerm->id ? 'splis-btn-primary' : 'splis-btn-secondary' }} splis-term-switcher-chip text-sm"
                @if ($term->id === $selectedTerm->id) aria-current="page" data-term-switcher-active @endif
            >
                {{ $term->label }}@if ($showCurrentBadge && $term->is_current) (current)@endif
            </a>
        @endforeach
    </div>

    <button
        type="button"
        class="splis-term-switcher-arrow"
        data-term-switcher-next
        hidden
        aria-label="Show older terms"
    >
        <x-icon name="arrow-right" class="h-4 w-4" />
    </button>
</nav>
