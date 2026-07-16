@props([
    'variant' => 'info',
])

@php
    $classes = match ($variant) {
        'success' => 'splis-alert-success',
        'error' => 'splis-alert-error',
        'warning' => 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100',
        default => 'rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200',
    };
@endphp

<div {{ $attributes->class([$classes, 'mb-6']) }}>
    {{ $slot }}
</div>
