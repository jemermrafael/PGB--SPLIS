@props(['session'])

@php
    $sessionDateOver = $session->isPastSessionDate();
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-end gap-2']) }}>
    @can('view', $session)
        <a href="{{ route('ob.sessions.show', $session) }}" class="splis-btn-secondary !px-2.5 !py-1.5 text-sm inline-flex items-center gap-1.5 whitespace-nowrap">
            <x-icon name="eye" class="h-4 w-4 shrink-0" />
            View
        </a>
    @endcan

    @if (auth()->user()?->canRecordAttendance())
        <a href="{{ route('ob.sessions.attendance', $session) }}" class="splis-btn-secondary !px-2.5 !py-1.5 text-sm inline-flex items-center gap-1.5 whitespace-nowrap">
            <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
            Attendance
        </a>
    @endif

    @if ($session->obDocument)
        @can('update', $session->obDocument)
            @unless ($sessionDateOver)
                <a href="{{ route('ob.document.maker', $session) }}" class="splis-btn-secondary !px-2.5 !py-1.5 text-sm inline-flex items-center gap-1.5 whitespace-nowrap">
                    <x-icon name="edit" class="h-4 w-4 shrink-0" />
                    Maker
                </a>
            @endunless
        @endcan
        @can('view', $session->obDocument)
            <a href="{{ route('ob.document.print', $session) }}" target="_blank" class="splis-btn-secondary !px-2.5 !py-1.5 text-sm inline-flex items-center gap-1.5 whitespace-nowrap">
                <x-icon name="printer" class="h-4 w-4 shrink-0" />
                Print
            </a>
        @endcan
    @endif
</div>
