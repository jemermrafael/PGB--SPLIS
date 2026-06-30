@php
    use App\Support\KeywordList;

    $keywords = KeywordList::split($value ?? '');
@endphp

@if ($keywords !== [])
    <div class="splis-keyword-links">
        @foreach ($keywords as $keyword)
            <a
                href="{{ ($searchUrl ?? route('resolutions.index')).'?keyword='.urlencode($keyword) }}"
                class="splis-keyword-link"
            >{{ $keyword }}</a>
        @endforeach
    </div>
@endif
