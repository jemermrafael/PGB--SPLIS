@php
    $map = \App\Support\BataanPoliticalMap::svgPaths();
    $dataBySlug = collect($municipalities)->keyBy('slug');
@endphp

<div class="splis-bataan-map-wrap">
    <svg
        viewBox="{{ $map['viewBox'] }}"
        class="splis-bataan-svg"
        role="img"
        aria-label="Province of Bataan political map"
        @if (! empty($mapId))
            id="{{ $mapId }}"
        @endif
    >
        @foreach ($map['paths'] as $path)
            @php
                $mun = $dataBySlug->get($path['slug'], []);
                $total = (int) ($mun['total'] ?? $mun['requests'] ?? 0);
                $agendas = (int) ($mun['agendas'] ?? 0);
                $budget = (int) ($mun['budget'] ?? 0);
                $ordinances = (int) ($mun['ordinances'] ?? 0);
                $requests = (int) ($mun['requests'] ?? 0);
            @endphp
            <path
                data-geo-region
                data-slug="{{ $path['slug'] }}"
                data-name="{{ $path['name'] }}"
                data-agendas="{{ $agendas }}"
                data-total="{{ $total }}"
                data-requests="{{ $requests }}"
                data-budget="{{ $budget }}"
                data-ordinances="{{ $ordinances }}"
                d="{{ $path['d'] }}"
                class="splis-bataan-region"
                title="{{ $path['name'] }}"
            />
            <text
                x="{{ number_format($path['centroid'][0], 2, '.', '') }}"
                y="{{ number_format($path['centroid'][1], 2, '.', '') }}"
                class="splis-bataan-label"
                text-anchor="middle"
                dominant-baseline="middle"
                pointer-events="none"
            >
                <tspan x="{{ number_format($path['centroid'][0], 2, '.', '') }}" dy="-0.4em" class="splis-bataan-label-name">{{ $path['name'] }}</tspan>
                <tspan x="{{ number_format($path['centroid'][0], 2, '.', '') }}" dy="1.2em" class="splis-bataan-label-value" data-geo-region-value>—</tspan>
            </text>
        @endforeach
    </svg>

    <div class="splis-geo-legend">
        <span class="splis-geo-legend-label">Low</span>
        <div class="splis-geo-legend-bar" aria-hidden="true"></div>
        <span class="splis-geo-legend-label">High</span>
    </div>
</div>
