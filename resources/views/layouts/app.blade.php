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
        $user = auth()->user();
        $isBoardMember = $user?->isBoardMember() ?? false;
        $isMunicipalViewer = $user?->isMunicipalViewer() ?? false;
        $canAdmin = $user?->canAdmin() ?? false;
        $showNotifications = $isBoardMember || $canAdmin;
        $incomingEnabled = config('incoming.enabled', false);
        $ordinancesNavActive = request()->routeIs('ordinances.*')
            || request()->routeIs('appropriation-ordinances.*')
            || request()->routeIs('board-member.ordinances.*');
        $myOrdinancesNavActive = request()->routeIs('board-member.ordinances.*');
        $myAgendaNavActive = request()->routeIs('board-member.agenda.*')
            || ($isBoardMember && request()->routeIs('agenda.*'));

        $myRequestsNavActive = request()->routeIs('municipal.requests.*');

        $navItems = [
            ['label' => 'Dashboard', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
        ];

        if (! $isBoardMember && ! $isMunicipalViewer) {
            $navItems[] = ['label' => 'Agenda', 'url' => route('agenda.index'), 'active' => request()->routeIs('agenda.*')];
            $navItems[] = ['label' => 'Order of Business', 'url' => route('ob.sessions.index'), 'active' => request()->routeIs('ob.*')];
            $navItems[] = ['label' => 'Reference Materials', 'url' => route('references.index'), 'active' => request()->routeIs('references.*')];
        } elseif ($isBoardMember) {
            $navItems[] = ['label' => 'Order of Business', 'url' => route('ob.sessions.index'), 'active' => request()->routeIs('ob.*')];
        }

        $resolutionsNavActive = request()->routeIs('resolutions.*') || ($incomingEnabled && request()->routeIs('incoming.*'));
        $committeesNavActive = request()->routeIs('committees.*')
            || request()->routeIs('board-members.*')
            || request()->routeIs('committee-terms.*')
            || request()->routeIs('committee-monitoring.*')
            || request()->routeIs('admin.board-member-ordinances');
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
                            <button type="button" id="splis-a11y-trigger" class="splis-header-btn splis-header-btn-icon" data-dropdown-trigger aria-expanded="false" aria-controls="splis-a11y-panel" aria-haspopup="true" aria-label="Accessibility">
                                <svg viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576C178.6 576 64 461.4 64 320zM225.5 233.9C213.3 228.7 199.2 234.3 194 246.5C188.8 258.7 194.4 272.8 206.6 278L218.5 283.1C235.8 290.5 253.7 296 272.1 299.4L272.1 349.5C272.1 353.8 271.4 358.1 270 362.1L241.3 448.2C237.1 460.8 243.9 474.4 256.5 478.6C269.1 482.8 282.7 476 286.9 463.4L311.3 390.2C312.6 386.4 316.1 383.8 320.1 383.8C324.1 383.8 327.7 386.4 328.9 390.2L353.3 463.4C357.5 476 371.1 482.8 383.7 478.6C396.3 474.4 403 461 398.8 448.4L370.1 362.3C368.7 358.2 368 354 368 349.7L368 299.6C386.4 296.1 404.3 290.7 421.6 283.3L433.5 278.2C445.7 273 451.3 258.9 446.1 246.7C440.9 234.5 426.8 228.9 414.6 234.1L402.7 239C376.6 250.2 348.5 256 320 256C291.5 256 263.5 250.2 237.3 239L225.4 233.9zM320 224C342.1 224 360 206.1 360 184C360 161.9 342.1 144 320 144C297.9 144 280 161.9 280 184C280 206.1 297.9 224 320 224z"/></svg>
                            </button>
                            @include('partials.accessibility-panel')
                        </div>

                        @if ($showNotifications)
                            @include('partials.header-notifications')
                        @endif

                        <div class="splis-user-menu" data-dropdown>
                            <button type="button" class="splis-header-btn splis-user-menu-trigger" data-dropdown-trigger aria-expanded="false" aria-haspopup="true">
                                <span class="hidden sm:inline">{{ auth()->user()->role->label() }}</span>
                                <span class="sm:hidden">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                <svg class="h-4 w-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                            </button>
                            <div class="splis-user-menu-panel" data-dropdown-panel>
                                <p class="px-3 py-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</p>
                                <p class="px-3 pb-2 text-xs text-slate-500">{{ auth()->user()->role->label() }}</p>
                                @if (auth()->user()->canAdmin())
                                    <a href="{{ route('admin.analytics.index') }}" class="splis-user-menu-link">Executive dashboard</a>
                                @endif
                                @if (auth()->user()->canManageUsers())
                                    <a href="{{ route('users.index') }}" class="splis-user-menu-link">Manage users</a>
                                    <a href="{{ route('admin.data-sync.index') }}" class="splis-user-menu-link">Data sync</a>
                                    <a href="{{ route('admin.backups.index') }}" class="splis-user-menu-link">Database backups</a>
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

                    @if ($isMunicipalViewer)
                    <a
                        href="{{ route('municipal.requests.index') }}"
                        @class([
                            'splis-navbar-link',
                            'splis-navbar-link-active' => $myRequestsNavActive,
                        ])
                    >
                        My Requests
                    </a>
                    @endif

                    @if (! $isBoardMember && ! $isMunicipalViewer)
                    <a
                        href="{{ route('resolutions.index') }}"
                        @class([
                            'splis-navbar-link',
                            'splis-navbar-link-active' => request()->routeIs('resolutions.*'),
                        ])
                    >
                        Resolutions
                    </a>
                    @if ($incomingEnabled)
                        <a
                            href="{{ route('incoming.index') }}"
                            @class([
                                'splis-navbar-link',
                                'splis-navbar-link-active' => request()->routeIs('incoming.*'),
                            ])
                        >
                            Incoming
                        </a>
                    @endif
                    @endif

                    @if (! $isBoardMember && ! $isMunicipalViewer)
                    <div class="splis-nav-dropdown" data-dropdown>
                        <button
                            type="button"
                            data-dropdown-trigger
                            aria-expanded="false"
                            aria-haspopup="true"
                            @class([
                                'splis-navbar-link splis-nav-dropdown-trigger',
                                'splis-navbar-link-active' => $ordinancesNavActive,
                            ])
                        >
                            Ordinances
                            <svg class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div class="splis-nav-dropdown-panel" data-dropdown-panel role="menu">
                            <a href="{{ route('ordinances.index') }}" role="menuitem" @class(['splis-nav-dropdown-link', 'splis-nav-dropdown-link-active' => request()->routeIs('ordinances.*') && ! request()->routeIs('appropriation-ordinances.*')])>Provincial Ordinances</a>
                            <a href="{{ route('appropriation-ordinances.index') }}" role="menuitem" @class(['splis-nav-dropdown-link', 'splis-nav-dropdown-link-active' => request()->routeIs('appropriation-ordinances.*')])>Appropriation Ordinances</a>
                        </div>
                    </div>
                    @endif

                    @if ($isBoardMember)
                    <div class="splis-nav-dropdown" data-dropdown>
                        <button
                            type="button"
                            data-dropdown-trigger
                            aria-expanded="false"
                            aria-haspopup="true"
                            @class([
                                'splis-navbar-link splis-nav-dropdown-trigger',
                                'splis-navbar-link-active' => $myAgendaNavActive,
                            ])
                        >
                            My Agenda
                            <svg class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div class="splis-nav-dropdown-panel" data-dropdown-panel role="menu">
                            <a href="{{ route('board-member.agenda.index') }}" role="menuitem" @class(['splis-nav-dropdown-link', 'splis-nav-dropdown-link-active' => request()->routeIs('board-member.agenda.*')])>My Agenda</a>
                            <a href="{{ route('agenda.index') }}" role="menuitem" @class(['splis-nav-dropdown-link', 'splis-nav-dropdown-link-active' => request()->routeIs('agenda.*') && ! request()->routeIs('board-member.agenda.*')])>All Agenda</a>
                        </div>
                    </div>
                    @endif

                    @if ($isBoardMember)
                    <div class="splis-nav-dropdown" data-dropdown>
                        <button
                            type="button"
                            data-dropdown-trigger
                            aria-expanded="false"
                            aria-haspopup="true"
                            @class([
                                'splis-navbar-link splis-nav-dropdown-trigger',
                                'splis-navbar-link-active' => $myOrdinancesNavActive,
                            ])
                        >
                            My Ordinances
                            <svg class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div class="splis-nav-dropdown-panel" data-dropdown-panel role="menu">
                            <a href="{{ route('board-member.ordinances.index') }}" role="menuitem" @class(['splis-nav-dropdown-link', 'splis-nav-dropdown-link-active' => request()->routeIs('board-member.ordinances.index')])>My Ordinances</a>
                            <a href="{{ route('board-member.ordinances.all') }}" role="menuitem" @class(['splis-nav-dropdown-link', 'splis-nav-dropdown-link-active' => request()->routeIs('board-member.ordinances.all')])>All Ordinances</a>
                        </div>
                    </div>
                    @endif

                    @if (! $isBoardMember && ! $isMunicipalViewer)
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
                                Board Members
                            </a>
                            <a
                                href="{{ route('committee-monitoring.index') }}"
                                role="menuitem"
                                @class([
                                    'splis-nav-dropdown-link',
                                    'splis-nav-dropdown-link-active' => request()->routeIs('committee-monitoring.*'),
                                ])
                            >
                                Committee Monitoring
                            </a>
                            @if ($user?->canRecordAttendance())
                                <a href="{{ route('ob.sessions.attendance.monthly') }}" role="menuitem" @class(['splis-nav-dropdown-link', 'splis-nav-dropdown-link-active' => request()->routeIs('ob.sessions.attendance.monthly')])>Monthly Attendance</a>
                                <a href="{{ route('admin.board-member-ordinances') }}" role="menuitem" @class(['splis-nav-dropdown-link', 'splis-nav-dropdown-link-active' => request()->routeIs('admin.board-member-ordinances')])>BM Authored Ordinances</a>
                            @endif
                        </div>
                    </div>
                    @endif

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
            <div @class([
                'splis-page',
                'splis-page--full' => trim($__env->yieldContent('full_width')),
            ])>
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

    @if ($showNotifications)
        <div id="splis-toast-stack" class="splis-toast-stack" aria-live="polite" aria-atomic="true"></div>
    @endif

    @if ($user?->isSuperadmin())
        @include('partials.confirm-dialog')
    @endif

    @stack('scripts')
</body>
</html>
