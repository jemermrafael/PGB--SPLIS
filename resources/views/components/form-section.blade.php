@props([
    'title',
    'subtitle' => null,
])

<section {{ $attributes->class(['splis-form-section']) }}>
    <div class="splis-form-section-header">
        <h2 class="splis-form-section-title">{{ $title }}</h2>
        @if ($subtitle)
            <p class="splis-form-section-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="splis-form-section-body">
        {{ $slot }}
    </div>
</section>
