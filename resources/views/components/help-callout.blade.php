@props([
    'title' => 'Tip',
])

<aside {{ $attributes->class(['splis-help-callout']) }}>
    <p class="splis-help-callout-title">{{ $title }}</p>
    <div class="splis-help-callout-body">
        {{ $slot }}
    </div>
</aside>
