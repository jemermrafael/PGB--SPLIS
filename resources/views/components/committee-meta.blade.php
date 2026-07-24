@props([
    'name' => '',
    'committee' => null,
    'showLabel' => true,
])

@php
    /** @var \App\Models\Committee|null $committee */
    $label = trim((string) ($name !== '' ? $name : ($committee?->name ?? '')));
    $customUrl = \App\Support\CommitteeIcon::customUrl($committee);
    $key = \App\Support\CommitteeIcon::resolveKey($committee, $label);
    $path = \App\Support\CommitteeIcon::pathFor($key);
@endphp

@if ($label === '')
    <span class="text-slate-400">—</span>
@elseif ($showLabel)
    <span {{ $attributes->class('splis-list-committee') }} title="{{ $label }}">
        <span class="splis-list-committee-icon" aria-hidden="true">
            @if ($customUrl)
                <span class="splis-list-committee-icon-glyph" style="--committee-icon: url('{{ $customUrl }}')"></span>
            @else
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                </svg>
            @endif
        </span>
        <span class="splis-list-committee-text">{{ $label }}</span>
    </span>
@else
    <span {{ $attributes->class('splis-list-committee-icon inline-flex') }} aria-hidden="true" title="{{ $label }}">
        @if ($customUrl)
            <span class="splis-list-committee-icon-glyph" style="--committee-icon: url('{{ $customUrl }}')"></span>
        @else
            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
            </svg>
        @endif
    </span>
@endif
