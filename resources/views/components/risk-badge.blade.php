@props([
    'level' => 'safe',
    'label' => null,
])

@php
    $label ??= match ($level) {
        'caution' => 'Caution',
        'danger' => 'Destructive',
        'maintenance' => 'Maintenance',
        default => 'Safe',
    };

    $class = match ($level) {
        'caution', 'maintenance' => 'splis-risk-badge--caution',
        'danger' => 'splis-risk-badge--danger',
        default => 'splis-risk-badge--safe',
    };
@endphp

<span {{ $attributes->class(['splis-risk-badge', $class]) }}>{{ $label }}</span>
