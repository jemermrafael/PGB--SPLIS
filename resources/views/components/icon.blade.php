@props([
    'name',
    'class' => 'h-4 w-4',
    'strokeWidth' => '1.75',
])

<svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" aria-hidden="true">
    @switch($name)
        @case('menu-2')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            @break

        @case('user-circle')
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7a4 4 0 1 1 8 0a4 4 0 0 1-8 0" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 21a6 6 0 0 1 12 0" />
            @break

        @case('chevron-down')
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            @break

        @case('trash')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 11v6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 11v6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3" />
            @break

        @case('plus')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
            @break

        @case('eye')
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12s3.6-6 9-6 9 6 9 6-3.6 6-9 6-9-6-9-6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9a3 3 0 1 1 0 6a3 3 0 0 1 0-6" />
            @break

        @case('check-circle')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 1 1 0 18a9 9 0 0 1 0-18" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" />
            @break

        @case('edit')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z" />
            @break

        @case('printer')
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18H5a2 2 0 0 1-2-2v-4a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v4a2 2 0 0 1-2 2h-1" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 14h12v7H6z" />
            @break

        @case('download')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m7 10 5 5 5-5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 21h14" />
            @break

        @case('external-link')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 13 20 4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 4h5v5" />
            @break

        @case('sparkles')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 12h3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m6.3 6.3 2.1 2.1" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m15.6 15.6 2.1 2.1" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m17.7 6.3-2.1 2.1" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.4 15.6-2.1 2.1" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" />
            @break

        @case('archive')
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 7l1 11a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-11" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 11h6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l1 4H8l1-4Z" />
            @break

        @case('folder')
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
            @break

        @case('bell')
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 18a2 2 0 1 0 4 0" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.26 15.29h13.48a1 1 0 0 0 .86-1.51A2 2 0 0 1 19.33 13A6.67 6.67 0 0 0 18 9a6 6 0 1 0-12 0a6.67 6.67 0 0 0-1.33 4c0 .28-.1.55-.27.78a1 1 0 0 0 .86 1.51" />
            @break

        @case('arrow-left')
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 6 6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 6-6" />
            @break

        @case('arrow-right')
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m13 6 6 6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m13 18 6-6" />
            @break

        @case('layout-dashboard')
            <rect x="3" y="3" width="7" height="9" rx="1" />
            <rect x="14" y="3" width="7" height="5" rx="1" />
            <rect x="14" y="12" width="7" height="9" rx="1" />
            <rect x="3" y="16" width="7" height="5" rx="1" />
            @break

        @case('file-text')
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V8l-4-5Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17h6" />
            @break

        @case('inbox')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7l2-3h12l2 3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6" />
            @break

        @case('scale')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z" />
            @break

        @case('users')
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 19a4 4 0 0 0-8 0" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 11a3 3 0 1 0 0-6a3 3 0 0 0 0 6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 19a3 3 0 0 0-2.2-2.9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 19a3 3 0 0 1 2.2-2.9" />
            @break

        @case('meeting')
            <circle cx="7" cy="7.5" r="2" />
            <circle cx="12" cy="6.5" r="2.25" />
            <circle cx="17" cy="7.5" r="2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 16.5c.4-2.2 2-3.5 3.5-3.5s3.1 1.3 3.5 3.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.5 15.2c.6-1.7 1.9-2.7 3.5-2.7s2.9 1 3.5 2.7" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.5c.4-2.2 2-3.5 3.5-3.5s3.1 1.3 3.5 3.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5h16" />
            <circle cx="9.25" cy="11.25" r="0.7" fill="currentColor" stroke="none" />
            <circle cx="12" cy="10.5" r="0.7" fill="currentColor" stroke="none" />
            <circle cx="14.75" cy="11.25" r="0.7" fill="currentColor" stroke="none" />
            @break

        @case('calendar')
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 3v3" />
            <rect x="4" y="6" width="16" height="15" rx="2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 11h16" />
            @break

        @case('book')
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 4h9a3 3 0 0 1 3 3v13H8a3 3 0 0 0-3 3V4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 4a3 3 0 0 0-3 3v13" />
            @break

        @case('book-closed')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8" />
            @break

        @case('notebook')
            <rect x="6" y="3" width="13" height="18" rx="2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 11h2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 15h2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 8h6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 12h6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 16h4" />
            @break

        @case('chart-bar')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 20V4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M22 20v-9" />
            @break

        @case('database')
            <ellipse cx="12" cy="6" rx="7" ry="3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3" />
            @break

        @case('refresh')
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 12a8 8 0 1 1-2.3-5.7" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 4v6h-6" />
            @break

        @case('shield')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 5-3.5 8.5-8 9c-4.5-.5-8-4-8-9V6l8-3" />
            @break

        @case('logout')
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 8V6a2 2 0 0 1 2-2h6v16h-6a2 2 0 0 1-2-2v-2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 12H4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m7 9-3 3 3 3" />
            @break

        @case('clipboard-list')
            <rect x="7" y="4" width="10" height="16" rx="2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 4h6v2H9z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 11h6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 15h6" />
            @break

        @case('calendar-check')
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 3v3" />
            <rect x="4" y="6" width="16" height="15" rx="2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 11h16" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 16 2 2 4-4" />
            @break

        @case('user')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 11a3 3 0 1 0 0-6a3 3 0 0 0 0 6" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 20a6 6 0 0 1 12 0" />
            @break

        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6" />
            @break

        @case('maximize')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 8V4h-4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v4h4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 16v4h-4" />
            @break

        @case('minimize')
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 4H4v4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 4h4v4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 20H4v-4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 20h4v-4" />
            @break

        @default
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16h.01" />
    @endswitch
</svg>
