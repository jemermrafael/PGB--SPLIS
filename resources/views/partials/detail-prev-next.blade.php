@if ($previous || $next)
    <nav class="splis-detail-nav" aria-label="{{ $label ?? 'Record navigation' }}">
        @if ($previous)
            <a href="{{ $previousUrl ?? $previous }}" class="splis-detail-nav-link">
                <span class="splis-detail-nav-direction">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Previous
                </span>
                <span class="splis-detail-nav-title">{{ $previousLabel ?? $previous }}</span>
            </a>
        @else
            <span class="splis-detail-nav-placeholder" aria-hidden="true"></span>
        @endif

        @if ($next)
            <a href="{{ $nextUrl ?? $next }}" class="splis-detail-nav-link splis-detail-nav-link--next">
                <span class="splis-detail-nav-direction">
                    Next
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </span>
                <span class="splis-detail-nav-title">{{ $nextLabel ?? $next }}</span>
            </a>
        @else
            <span class="splis-detail-nav-placeholder" aria-hidden="true"></span>
        @endif
    </nav>
@endif
