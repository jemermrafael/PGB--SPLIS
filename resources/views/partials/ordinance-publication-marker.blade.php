@php
    $status = $status ?? null;
@endphp

@if ($status)
    <span class="splis-ordinance-publication-marker {{ $status->badgeClass() }}" title="{{ $status->label() }}">
        <span class="{{ $status->markerDotClass() }}" aria-hidden="true"></span>
        <img
            src="{{ asset($status->iconPath()) }}"
            alt=""
            class="splis-ordinance-publication-icon splis-ordinance-publication-icon--sm"
            width="18"
            height="18"
        >
        <span>{{ $status->label() }}</span>
    </span>
@endif
