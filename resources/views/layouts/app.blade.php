<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <script>
        (function () {
            if (localStorage.getItem('splis-theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
            document.documentElement.classList.add('text-size-' + (localStorage.getItem('splis-text-size') || 'md'));
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    @php
        $navItems = [
            ['label' => 'Dashboard', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => 'Ordinances', 'url' => route('ordinances.index'), 'active' => request()->routeIs('ordinances.*')],
            ['label' => 'Agenda', 'url' => route('agenda.index'), 'active' => request()->routeIs('agenda.*')],
            ['label' => 'Order of Business', 'url' => route('ob.sessions.index'), 'active' => request()->routeIs('ob.*')],
            ['label' => 'Reference', 'url' => '#', 'active' => false, 'placeholder' => true],
        ];

        $resolutionsNavActive = request()->routeIs('resolutions.*') || request()->routeIs('incoming.*');
        $committeesNavActive = request()->routeIs('committees.*') || request()->routeIs('board-members.*') || request()->routeIs('committee-terms.*');
    @endphp

    <div class="flex min-h-screen flex-col">
        <header class="splis-shell-header">
            <div class="splis-header-top">
                <div class="splis-header-inner">
                    <a href="{{ route('dashboard') }}" class="splis-header-brand">
                        <img
                            src="{{ asset('images/bataan-seal.png') }}"
                            alt="Province of Bataan official seal"
                            class="splis-header-seal"
                        >
                        <div class="min-w-0">
                            <p class="splis-header-eyebrow">Legislative Information System</p>
                            <p class="splis-header-title">Sangguniang Panlalawigan</p>
                        </div>
                    </a>

                    <div class="splis-header-actions">
                        <span class="splis-header-date hidden lg:inline">{{ now()->format('M j, Y') }}</span>

                        <div class="splis-a11y-wrap" data-dropdown>
                            <button type="button" id="splis-a11y-trigger" class="splis-header-btn" data-dropdown-trigger aria-expanded="false" aria-controls="splis-a11y-panel" aria-haspopup="true">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                                Accessibility
                            </button>
                            @include('partials.accessibility-panel')
                        </div>

                        <div class="splis-user-menu" data-dropdown>
                            <button type="button" class="splis-header-btn splis-user-menu-trigger" data-dropdown-trigger aria-expanded="false" aria-haspopup="true">
                                <span class="hidden sm:inline">{{ auth()->user()->role->label() }}</span>
                                <span class="sm:hidden">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                <svg class="h-4 w-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                            </button>
                            <div class="splis-user-menu-panel" data-dropdown-panel>
                                <p class="px-3 py-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</p>
                                <p class="px-3 pb-2 text-xs text-slate-500">{{ auth()->user()->role->label() }}</p>
                                @if (auth()->user()->canManageUsers())
                                    <a href="{{ route('users.index') }}" class="splis-user-menu-link">Manage users</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="splis-user-menu-signout">Sign out</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="splis-header-navbar" aria-label="Main navigation">
                <div class="splis-header-navbar-inner">
                    <a
                        href="{{ route('dashboard') }}"
                        @class([
                            'splis-navbar-link',
                            'splis-navbar-link-active' => request()->routeIs('dashboard'),
                        ])
                    >
                        Dashboard
                    </a>

                    <div class="splis-nav-dropdown" data-dropdown>
                        <button
                            type="button"
                            data-dropdown-trigger
                            aria-expanded="false"
                            aria-haspopup="true"
                            @class([
                                'splis-navbar-link splis-nav-dropdown-trigger',
                                'splis-navbar-link-active' => $resolutionsNavActive,
                            ])
                        >
                            Resolutions
                            <svg class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div class="splis-nav-dropdown-panel" data-dropdown-panel role="menu">
                            <a
                                href="{{ route('resolutions.index') }}"
                                role="menuitem"
                                @class([
                                    'splis-nav-dropdown-link',
                                    'splis-nav-dropdown-link-active' => request()->routeIs('resolutions.*'),
                                ])
                            >
                                All Resolutions
                            </a>
                            <a
                                href="{{ route('incoming.index') }}"
                                role="menuitem"
                                @class([
                                    'splis-nav-dropdown-link',
                                    'splis-nav-dropdown-link-active' => request()->routeIs('incoming.*'),
                                ])
                            >
                                Incoming
                            </a>
                        </div>
                    </div>

                    <div class="splis-nav-dropdown" data-dropdown>
                        <button
                            type="button"
                            data-dropdown-trigger
                            aria-expanded="false"
                            aria-haspopup="true"
                            @class([
                                'splis-navbar-link splis-nav-dropdown-trigger',
                                'splis-navbar-link-active' => $committeesNavActive,
                            ])
                        >
                            Committees
                            <svg class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div class="splis-nav-dropdown-panel" data-dropdown-panel role="menu">
                            <a
                                href="{{ route('committees.index') }}"
                                role="menuitem"
                                @class([
                                    'splis-nav-dropdown-link',
                                    'splis-nav-dropdown-link-active' => request()->routeIs('committees.*'),
                                ])
                            >
                                Committees
                            </a>
                            <a
                                href="{{ route('board-members.index') }}"
                                role="menuitem"
                                @class([
                                    'splis-nav-dropdown-link',
                                    'splis-nav-dropdown-link-active' => request()->routeIs('board-members.*'),
                                ])
                            >
                                Board members
                            </a>
                        </div>
                    </div>

                    @foreach ($navItems as $item)
                        @if ($item['label'] === 'Dashboard')
                            @continue
                        @endif
                        <a
                            href="{{ $item['url'] }}"
                            @class([
                                'splis-navbar-link',
                                'splis-navbar-link-active' => $item['active'],
                                'splis-navbar-link-placeholder' => ! empty($item['placeholder']),
                            ])
                            @if (! empty($item['placeholder'])) aria-disabled="true" @endif
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </nav>
        </header>

        <main class="splis-main flex-1">
            <div class="splis-page">
                @if (session('status'))
                    <div class="splis-alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="splis-alert-error">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
</body>
</html>
