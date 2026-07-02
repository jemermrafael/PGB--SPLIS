@php
    $status = $status ?? null;
@endphp

@if ($status)
    <span class="{{ $status->showButtonClass() }}" role="status">
        <img
            src="{{ asset($status->iconPath()) }}"
            alt=""
            class="splis-ordinance-status-btn-icon"
            width="20"
            height="20"
        >
        <span>{{ $status->label() }}</span>
    </span>
@endif
