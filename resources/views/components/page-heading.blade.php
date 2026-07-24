@props([
    'title',
    'subtitle' => null,
    'icon' => 'file-text',
])

@php
    $icons = [
        'ordinances' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z"/>',
        'file-text' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
        'inbox' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.09 9.09 0 003.74-.72 4.5 4.5 0 00-7.86-2.72M18 18.72v0a5.25 5.25 0 00-.75-2.72m.75 2.72A11.95 11.95 0 0112 21c-2.17 0-4.21-.58-5.98-1.59M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM4.92 18.72A9 9 0 0112 15.75c.87 0 1.71.12 2.5.35"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 19.5c.8-3.2 3.4-5 6.5-5s5.7 1.8 6.5 5"/>',
        'meeting' => '<circle cx="7" cy="7.5" r="2"/><circle cx="12" cy="6.5" r="2.25"/><circle cx="17" cy="7.5" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 16.5c.4-2.2 2-3.5 3.5-3.5s3.1 1.3 3.5 3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M8.5 15.2c.6-1.7 1.9-2.7 3.5-2.7s2.9 1 3.5 2.7"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.5c.4-2.2 2-3.5 3.5-3.5s3.1 1.3 3.5 3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5h16"/><circle cx="9.25" cy="11.25" r="0.7" fill="currentColor" stroke="none"/><circle cx="12" cy="10.5" r="0.7" fill="currentColor" stroke="none"/><circle cx="14.75" cy="11.25" r="0.7" fill="currentColor" stroke="none"/>',
        'clipboard' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 3h6m-6 3h6M8.25 3.75h7.5A2.25 2.25 0 0118 6v12.75A2.25 2.25 0 0115.75 21h-7.5A2.25 2.25 0 016 18.75V6a2.25 2.25 0 012.25-2.25z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75V3a1.5 1.5 0 011.5-1.5h3A1.5 1.5 0 0115 3v.75"/>',
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>',
        'agenda' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 3v3"/><rect x="4" y="6" width="16" height="15" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 11h16"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 16 2 2 4-4"/>',
        'book' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>',
        'notebook' => '<rect x="6" y="3" width="13" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h2"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 11h2"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 15h2"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 8h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 12h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 16h4"/>',
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
        'monitor' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z"/>',
        'clipboard-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75"/>',
    ];

    $path = $icons[$icon] ?? $icons['file-text'];
@endphp

<div {{ $attributes->class('splis-page-heading') }}>
    <div class="splis-page-heading-badge" aria-hidden="true">
        <svg class="splis-page-heading-glyph-simple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            {!! $path !!}
        </svg>
    </div>
    <div class="splis-page-heading-copy">
        <h1 class="splis-page-heading-title">{{ $title }}</h1>
        @if ($slot->isNotEmpty())
            <p class="splis-page-heading-subtitle">{!! $slot !!}</p>
        @elseif (filled($subtitle))
            <p class="splis-page-heading-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
</div>
