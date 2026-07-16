@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class(['splis-page-header']) }}>
    <div class="min-w-0">
        @isset($badges)
            <div class="mb-2 flex flex-wrap items-center gap-2">
                {{ $badges }}
            </div>
        @endisset
        <h1 class="splis-page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="splis-page-subtitle">{{ $subtitle }}</p>
        @endif
        @isset($meta)
            <div class="mt-2">{{ $meta }}</div>
        @endisset
    </div>
    @isset($actions)
        <div class="splis-page-header-actions">
            {{ $actions }}
        </div>
    @endisset
</div>
