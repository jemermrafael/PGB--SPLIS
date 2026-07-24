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
        $showNotifications = $user?->receivesInAppNotifications() ?? false;
        $incomingEnabled = config('incoming.enabled', false);
        $ordinancesNavActive = request()->routeIs('ordinances.*')
            || request()->routeIs('appropriation-ordinances.*')
            || request()->routeIs('board-member.ordinances.*');
        $myOrdinancesNavActive = request()->routeIs('board-member.ordinances.*');
        $myAgendaNavActive = request()->routeIs('board-member.agenda.*')
            || ($isBoardMember && request()->routeIs('agenda.*'));
        $myCommitteesNavActive = request()->routeIs('board-member.committees.*');
        $myProfileNavActive = request()->routeIs('board-member.profile.*');

        $myRequestsNavActive = request()->routeIs('municipal.requests.*');

        $navItems = [
            ['label' => 'Dashboard', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'layout-dashboard'],
        ];

        if (! $isBoardMember && ! $isMunicipalViewer) {
            $navItems[] = ['label' => 'Agenda', 'url' => route('agenda.index'), 'active' => request()->routeIs('agenda.*'), 'icon' => 'calendar-check'];
            $navItems[] = ['label' => 'Order of Business', 'url' => route('ob.sessions.index'), 'active' => request()->routeIs('ob.*'), 'icon' => 'calendar'];
            $navItems[] = ['label' => 'Reference Materials', 'url' => route('references.index'), 'active' => request()->routeIs('references.*'), 'icon' => 'book'];
            if (($user?->canEncode() ?? false) || ($user?->canAdmin() ?? false)) {
                $navItems[] = ['label' => 'Directory', 'url' => route('directory.index'), 'active' => request()->routeIs('directory.*'), 'icon' => 'notebook'];
            }
        } elseif ($isBoardMember) {
            $navItems[] = ['label' => 'My Committees', 'url' => route('board-member.committees.index'), 'active' => $myCommitteesNavActive, 'icon' => 'users'];
            $navItems[] = ['label' => 'Order of Business', 'url' => route('ob.sessions.index'), 'active' => request()->routeIs('ob.*'), 'icon' => 'calendar'];
            $navItems[] = ['label' => 'Committee Reports', 'url' => route('board-member.committee-reports.index'), 'active' => request()->routeIs('board-member.committee-reports.*'), 'icon' => 'file-text'];
        }

        $resolutionsNavActive = request()->routeIs('resolutions.*') || ($incomingEnabled && request()->routeIs('incoming.*'));
        $committeesNavActive = request()->routeIs('committees.*')
            || request()->routeIs('board-members.*')
            || request()->routeIs('committee-terms.*')
            || request()->routeIs('committee-monitoring.*')
            || request()->routeIs('committee-reports.*')
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

                        <button
                            type="button"
                            id="splis-nav-toggle"
                            class="splis-header-btn splis-header-btn-icon lg:hidden"
                            aria-expanded="false"
                            aria-controls="splis-main-nav"
                            aria-label="Open menu"
                        >
                            <x-icon name="menu-2" class="h-6 w-6" stroke-width="1.8" />
                        </button>

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
                            <button type="button" class="splis-header-btn splis-user-menu-trigger" data-dropdown-trigger aria-expanded="false" aria-haspopup="true" aria-label="Account menu">
                                <x-icon name="user-circle" class="h-5 w-5 lg:hidden" stroke-width="1.8" />
                                <span class="hidden lg:inline">{{ auth()->user()->role->label() }}</span>
                                <x-icon name="chevron-down" class="hidden h-4 w-4 opacity-70 lg:inline" stroke-width="2" />
                            </button>
                            <div class="splis-user-menu-panel" data-dropdown-panel>
                                <p class="px-3 py-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</p>
                                <p class="px-3 pb-2 text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->role->label() }}</p>
                                @if (auth()->user()->isBoardMember())
                                    <a href="{{ route('board-member.profile.edit') }}" @class(['splis-user-menu-link inline-flex items-center gap-2', 'font-semibold' => $myProfileNavActive])>
                                        <x-icon name="user" class="h-4 w-4 shrink-0 opacity-80" />
                                        My Profile
                                    </a>
                                    <a href="{{ route('board-member.committees.index') }}" class="splis-user-menu-link inline-flex items-center gap-2">
                                        <x-icon name="users" class="h-4 w-4 shrink-0 opacity-80" />
                                        My Committees
                                    </a>
                                @endif
                                @if (auth()->user()->canAdmin() || auth()->user()->isBoardMember())
                                    <a href="{{ route('admin.analytics.index') }}" class="splis-user-menu-link inline-flex items-center gap-2">
                                        <x-icon name="chart-bar" class="h-4 w-4 shrink-0 opacity-80" />
                                        Executive Dashboard
                                    </a>
                                @endif
                                @if (auth()->user()->canManageUsers())
                                    <a href="{{ route('users.index') }}" class="splis-user-menu-link inline-flex items-center gap-2">
                                        <x-icon name="users" class="h-4 w-4 shrink-0 opacity-80" />
                                        Manage Users
                                    </a>
                                    <a href="{{ route('admin.data-sync.index') }}" class="splis-user-menu-link inline-flex items-center gap-2">
                                        <x-icon name="refresh" class="h-4 w-4 shrink-0 opacity-80" />
                                        Data Sync
                                    </a>
                                    <a href="{{ route('admin.backups.index') }}" class="splis-user-menu-link inline-flex items-center gap-2">
                                        <x-icon name="database" class="h-4 w-4 shrink-0 opacity-80" />
                                        Database Backups
                                    </a>
                                    <a href="{{ route('admin.icons.index') }}" class="splis-user-menu-link inline-flex items-center gap-2">
                                        <x-icon name="sparkles" class="h-4 w-4 shrink-0 opacity-80" />
                                        Icon Library
                                    </a>
                                    @php $trashTotal = \App\Http\Controllers\Admin\TrashController::totalCount(); @endphp
                                    <a href="{{ route('admin.trash.index') }}" class="splis-user-menu-link inline-flex items-center gap-2">
                                        <x-icon name="trash" class="h-4 w-4 shrink-0 opacity-80" stroke-width="1.8" />
                                        Trash
                                        @if ($trashTotal > 0)
                                            <span class="ml-auto tabular-nums text-xs opacity-70">({{ number_format($trashTotal) }})</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('admin.role-permissions.index') }}" class="splis-user-menu-link inline-flex items-center gap-2">
                                        <x-icon name="shield" class="h-4 w-4 shrink-0 opacity-80" />
                                        Role permissions
                                    </a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="splis-user-menu-signout inline-flex w-full items-center gap-2">
                                        <x-icon name="logout" class="h-4 w-4 shrink-0 opacity-80" />
                                        Sign out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="splis-header-navbar" id="splis-main-nav" aria-label="Main navigation">
                <div class="splis-header-navbar-inner">
                    <a
                        href="{{ route('dashboard') }}"
                        @class([
                            'splis-navbar-link inline-flex items-center gap-1.5',
                            'splis-navbar-link-active' => request()->routeIs('dashboard'),
                        ])
                    >
                        <x-icon name="layout-dashboard" class="h-4 w-4 shrink-0 opacity-80" />
                        Dashboard
                    </a>

                    @if ($isMunicipalViewer)
                    <a
                        href="{{ route('municipal.requests.index') }}"
                        @class([
                            'splis-navbar-link inline-flex items-center gap-1.5',
                            'splis-navbar-link-active' => $myRequestsNavActive,
                        ])
                    >
                        <x-icon name="inbox" class="h-4 w-4 shrink-0 opacity-80" />
                        My Requests
                    </a>
                    <a
                        href="{{ route('ordinances.index') }}"
                        @class([
                            'splis-navbar-link inline-flex items-center gap-1.5',
                            'splis-navbar-link-active' => request()->routeIs('ordinances.*'),
                        ])
                    >
                        <x-icon name="file-text" class="h-4 w-4 shrink-0 opacity-80" />
                        Ordinances
                    </a>
                    @endif

                    @if (! $isBoardMember && ! $isMunicipalViewer)
                    <a
                        href="{{ route('resolutions.index') }}"
                        @class([
                            'splis-navbar-link inline-flex items-center gap-1.5',
                            'splis-navbar-link-active' => request()->routeIs('resolutions.*'),
                        ])
                    >
                        <x-icon name="file-text" class="h-4 w-4 shrink-0 opacity-80" />
                        Resolutions
                    </a>
                    @if ($incomingEnabled)
                        <a
                            href="{{ route('incoming.index') }}"
                            @class([
                                'splis-navbar-link inline-flex items-center gap-1.5',
                                'splis-navbar-link-active' => request()->routeIs('incoming.*'),
                            ])
                        >
                            <x-icon name="inbox" class="h-4 w-4 shrink-0 opacity-80" />
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
                                'splis-navbar-link splis-nav-dropdown-trigger inline-flex items-center gap-1.5',
                                'splis-navbar-link-active' => $ordinancesNavActive,
                            ])
                        >
                            <x-icon name="scale" class="h-4 w-4 shrink-0 opacity-80" />
                            Ordinances
                            <x-icon name="chevron-down" class="ml-auto h-3.5 w-3.5 shrink-0 opacity-70" stroke-width="2" />
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
                                'splis-navbar-link splis-nav-dropdown-trigger inline-flex items-center gap-1.5',
                                'splis-navbar-link-active' => $myAgendaNavActive,
                            ])
                        >
                            <x-icon name="calendar-check" class="h-4 w-4 shrink-0 opacity-80" />
                            My Agenda
                            <x-icon name="chevron-down" class="ml-auto h-3.5 w-3.5 shrink-0 opacity-70" stroke-width="2" />
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
                                'splis-navbar-link splis-nav-dropdown-trigger inline-flex items-center gap-1.5',
                                'splis-navbar-link-active' => $myOrdinancesNavActive,
                            ])
                        >
                            <x-icon name="scale" class="h-4 w-4 shrink-0 opacity-80" />
                            My Ordinances
                            <x-icon name="chevron-down" class="ml-auto h-3.5 w-3.5 shrink-0 opacity-70" stroke-width="2" />
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
                                'splis-navbar-link splis-nav-dropdown-trigger inline-flex items-center gap-1.5',
                                'splis-navbar-link-active' => $committeesNavActive,
                            ])
                        >
                            <x-icon name="meeting" class="h-4 w-4 shrink-0 opacity-80" />
                            Committees
                            <x-icon name="chevron-down" class="ml-auto h-3.5 w-3.5 shrink-0 opacity-70" stroke-width="2" />
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
                            @can('viewAny', App\Models\BoardMemberCommitteeReport::class)
                                <a
                                    href="{{ route('committee-reports.index') }}"
                                    role="menuitem"
                                    @class([
                                        'splis-nav-dropdown-link',
                                        'splis-nav-dropdown-link-active' => request()->routeIs('committee-reports.*'),
                                    ])
                                >
                                    Committee Reports
                                </a>
                            @endcan
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
                                'splis-navbar-link inline-flex items-center gap-1.5',
                                'splis-navbar-link-active' => $item['active'],
                                'splis-navbar-link-placeholder' => ! empty($item['placeholder']),
                            ])
                            @if (! empty($item['placeholder'])) aria-disabled="true" @endif
                        >
                            @if (! empty($item['icon']))
                                <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 opacity-80" />
                            @endif
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

                @if (session('error'))
                    <div class="splis-alert-error">{{ session('error') }}</div>
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

        <div class="splis-opito-credit" aria-label="Developed by OPITO">
            <span class="splis-opito-credit-text">Developed by OPITO</span>
            <img
                src="{{ asset('images/pito-logo.png') }}"
                alt="OPITO"
                class="splis-opito-credit-logo"
                width="28"
                height="28"
            >
        </div>
    </div>

    @if ($showNotifications)
        <div id="splis-toast-stack" class="splis-toast-stack" aria-live="polite" aria-atomic="true"></div>
    @endif

    @include('partials.confirm-dialog')
    @include('partials.pdf-viewer-modal')

    @stack('scripts')
</body>
</html>
