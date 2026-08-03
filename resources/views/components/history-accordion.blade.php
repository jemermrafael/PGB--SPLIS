@props([
    'title',
    'subtitle' => null,
    'count' => 0,
    'threshold' => 2,
    'open' => false,
    'aside' => false,
])

@php
    $collapsible = (int) $count >= (int) $threshold;
    $tag = $aside ? 'aside' : 'div';
@endphp

@if ($collapsible)
    <details
        {{ $attributes->class(['splis-card mt-6 overflow-hidden splis-accordion']) }}
        @if ($open) open @endif
    >
        <summary class="splis-accordion-summary !px-5 !py-4">
            <div class="splis-accordion-summary-top">
                <div class="min-w-0">
                    <h2 class="splis-card-title">{{ $title }}</h2>
                    @if (filled($subtitle))
                        <p class="splis-card-subtitle">{{ $subtitle }}</p>
                    @endif
                </div>
                <span class="flex shrink-0 items-center gap-2">
                    @if (isset($actions))
                        <span onclick="event.preventDefault(); event.stopPropagation();">
                            {{ $actions }}
                        </span>
                    @endif
                    <span class="splis-accordion-count">{{ number_format((int) $count) }}</span>
                    <svg class="splis-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </span>
            </div>
        </summary>
        {{ $slot }}
    </details>
@else
    <{{ $tag }} {{ $attributes->class(['splis-card mt-6 overflow-hidden']) }}>
        <div class="splis-card-header splis-card-header--emphasis flex flex-wrap items-center justify-between gap-3 !border-b-0">
            <div class="min-w-0">
                <h2 class="splis-card-title">{{ $title }}</h2>
                @if (filled($subtitle))
                    <p class="splis-card-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            @if (isset($actions))
                <div class="shrink-0">
                    {{ $actions }}
                </div>
            @endif
        </div>
        {{ $slot }}
    </{{ $tag }}>
@endif
