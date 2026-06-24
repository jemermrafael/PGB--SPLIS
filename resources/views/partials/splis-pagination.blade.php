@php
    use Illuminate\Pagination\LengthAwarePaginator;

    /** @var LengthAwarePaginator $paginator */
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    $pages = collect([1, $last]);
    for ($i = $current - 2; $i <= $current + 2; $i++) {
        if ($i >= 1 && $i <= $last) {
            $pages->push($i);
        }
    }
    $pages = $pages->unique()->sort()->values();

    $visiblePages = [];
    $previous = null;
    foreach ($pages as $page) {
        if ($previous !== null && $page - $previous > 1) {
            $visiblePages[] = '…';
        }
        $visiblePages[] = $page;
        $previous = $page;
    }

    $queryExceptPage = request()->except('page');
@endphp

@if ($paginator->hasPages())
    <nav class="splis-pagination" aria-label="Pagination">
        <div class="splis-pagination-nav">
            <a href="{{ $paginator->url(1) }}" @class(['splis-btn-secondary splis-pagination-btn', 'pointer-events-none opacity-40' => $current <= 1]) title="First page">First</a>
            <a href="{{ $paginator->previousPageUrl() ?? '#' }}" @class(['splis-btn-secondary splis-pagination-btn', 'pointer-events-none opacity-40' => ! $paginator->previousPageUrl()]) title="Previous page">Prev</a>

            <div class="splis-pagination-pages">
                @foreach ($visiblePages as $page)
                    @if ($page === '…')
                        <span class="splis-pagination-ellipsis">…</span>
                    @elseif ($page === $current)
                        <span class="splis-pagination-page splis-pagination-page--active" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="splis-pagination-page">{{ $page }}</a>
                    @endif
                @endforeach
            </div>

            <a href="{{ $paginator->nextPageUrl() ?? '#' }}" @class(['splis-btn-secondary splis-pagination-btn', 'pointer-events-none opacity-40' => ! $paginator->nextPageUrl()]) title="Next page">Next</a>
            <a href="{{ $paginator->url($last) }}" @class(['splis-btn-secondary splis-pagination-btn', 'pointer-events-none opacity-40' => $current >= $last]) title="Last page">Last</a>
        </div>

        <form method="GET" action="{{ url()->current() }}" class="splis-pagination-goto">
            @foreach ($queryExceptPage as $key => $value)
                @if (is_array($value))
                    @foreach ($value as $item)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <label class="sr-only" for="splis-pagination-page-input">Page number</label>
            <input
                id="splis-pagination-page-input"
                type="number"
                name="page"
                min="1"
                max="{{ $last }}"
                value="{{ $current }}"
                class="splis-pagination-input"
                aria-label="Page number"
            >
            <button type="submit" class="splis-btn-secondary splis-pagination-btn">Go</button>
        </form>

        <p class="splis-pagination-meta">Page {{ $current }} of {{ $last }}</p>
    </nav>
@endif
