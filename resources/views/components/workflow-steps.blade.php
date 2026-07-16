@props([
    'steps' => [],
    'current' => 1,
])

@php
    /** @var array<int, array{label: string, description?: string, url?: string|null}> $steps */
    $current = max(1, (int) $current);
@endphp

<nav {{ $attributes->class(['splis-workflow-steps']) }} aria-label="Workflow progress">
    <ol class="splis-workflow-steps-list">
        @foreach ($steps as $index => $step)
            @php
                $number = $index + 1;
                $isComplete = $number < $current;
                $isCurrent = $number === $current;
                $url = $step['url'] ?? null;
            @endphp
            <li @class([
                'splis-workflow-step',
                'is-complete' => $isComplete,
                'is-current' => $isCurrent,
            ])>
                <span class="splis-workflow-step-index" aria-hidden="true">
                    @if ($isComplete)
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="min-w-0">
                    @if ($url && ($isComplete || $isCurrent))
                        <a href="{{ $url }}" class="splis-workflow-step-label hover:underline">{{ $step['label'] }}</a>
                    @else
                        <span class="splis-workflow-step-label">{{ $step['label'] }}</span>
                    @endif
                    @if (! empty($step['description']))
                        <span class="splis-workflow-step-desc">{{ $step['description'] }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ol>
</nav>
