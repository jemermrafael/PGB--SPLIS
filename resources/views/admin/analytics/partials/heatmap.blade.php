@php
    $format = $format ?? 'number';
    $max = collect($cells)->max('value') ?: 1;
@endphp
<div class="splis-exec-panel">
    <h2 class="splis-exec-panel-title">{{ $title }}</h2>
    @if (! empty($subtitle))
        <p class="splis-exec-panel-subtitle">{{ $subtitle }}</p>
    @endif
    @if ($cells === [])
        <p class="mt-4 text-sm text-slate-500">No data for this period.</p>
    @else
        <div class="splis-exec-heatmap mt-4">
            @foreach ($cells as $cell)
                @php
                    $intensity = max(0.12, ($cell['value'] ?? 0) / $max);
                    $display = $format === 'currency'
                        ? '₱'.number_format($cell['value'] ?? 0)
                        : number_format($cell['value'] ?? 0);
                @endphp
                <div class="splis-exec-heatmap-cell" style="--heat: {{ $intensity }}">
                    <span class="splis-exec-heatmap-label">{{ $cell['label'] ?? '—' }}</span>
                    <span class="splis-exec-heatmap-value">{{ $display }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
