@props(['session'])

<div {{ $attributes->merge(['class' => 'flex flex-nowrap items-center justify-end gap-3']) }}>
    @can('view', $session)
        <a href="{{ route('ob.sessions.show', $session) }}" class="splis-link inline-flex items-center gap-1.5 whitespace-nowrap">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            View
        </a>
    @endcan

    @if ($session->obDocument)
        @can('update', $session->obDocument)
            <a href="{{ route('ob.document.maker', $session) }}" class="splis-link inline-flex items-center gap-1.5 whitespace-nowrap">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                </svg>
                Maker
            </a>
        @endcan
        @can('view', $session->obDocument)
            <a href="{{ route('ob.document.print', $session) }}" target="_blank" class="splis-link inline-flex items-center gap-1.5 whitespace-nowrap">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18M6.34 18H4.5a2.25 2.25 0 01-2.25-2.25v-3.006A2.25 2.25 0 014.5 9.75h15a2.25 2.25 0 012.25 2.25v3.006A2.25 2.25 0 0119.5 18h-1.84M9.75 9.75h4.5V6.75a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75v3zM9.75 18v1.125c0 .621.504 1.125 1.125 1.125h2.25c.621 0 1.125-.504 1.125-1.125V18"/>
                </svg>
                Print
            </a>
        @endcan
    @endif
</div>
