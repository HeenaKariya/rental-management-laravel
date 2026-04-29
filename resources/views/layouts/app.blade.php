<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'PropMgr') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="app-body">
        @php
            /** @var \App\Models\User|null $authUser */
            $authUser = auth()->user();
            $isSuperAdmin = $authUser?->hasRole('super_admin');
            $isManager = $authUser?->hasRole('manager');
            $isTenant = $authUser?->hasRole('tenant');
            $appNavigation = collect([
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard', 'active' => request()->routeIs('dashboard'), 'visible' => true, 'badge' => null],
                ['label' => 'Properties', 'route' => 'properties.index', 'icon' => 'property', 'active' => request()->routeIs('properties.*'), 'visible' => $authUser?->hasAnyRole(['super_admin', 'manager']), 'badge' => 'Phase 2'],
                ['label' => 'Units', 'route' => 'units.index', 'icon' => 'unit', 'active' => request()->routeIs('units.*'), 'visible' => $authUser?->hasAnyRole(['super_admin', 'manager']), 'badge' => 'Phase 3'],
                ['label' => 'Tenants', 'route' => 'tenants.index', 'icon' => 'tenant', 'active' => request()->routeIs('tenants.*'), 'visible' => $authUser?->hasAnyRole(['super_admin', 'manager']), 'badge' => 'Phase 3'],
                ['label' => 'Leases', 'route' => 'leases.index', 'icon' => 'lease', 'active' => request()->routeIs('leases.*'), 'visible' => $authUser?->hasAnyRole(['super_admin', 'manager']), 'badge' => 'Phase 3'],
                ['label' => 'Deposits', 'route' => 'deposits.index', 'icon' => 'finance', 'active' => request()->routeIs('deposits.*'), 'visible' => $authUser?->hasAnyRole(['super_admin', 'manager']), 'badge' => 'Phase 3'],
                ['label' => 'Finance', 'route' => 'finance.index', 'icon' => 'finance', 'active' => request()->routeIs('finance.*'), 'visible' => $authUser?->hasAnyRole(['super_admin', 'manager']), 'badge' => 'Phase 4'],
                ['label' => 'Security', 'route' => 'settings.security', 'icon' => 'security', 'active' => request()->routeIs('settings.security*'), 'visible' => true, 'badge' => null],
                ['label' => '2FA Oversight', 'route' => 'admin.security.two-factor.index', 'icon' => 'shield', 'active' => request()->routeIs('admin.security.two-factor.*'), 'visible' => $isSuperAdmin, 'badge' => null],
                ['label' => 'Invitations', 'route' => 'invitations.create', 'icon' => 'invite', 'active' => request()->routeIs('invitations.create') || request()->routeIs('invitations.store'), 'visible' => $isSuperAdmin, 'badge' => null],
            ])->where('visible');
        @endphp

        <div class="app-shell">
            <aside class="app-sidebar" data-app-nav-panel>
                <div class="app-sidebar-top">
                    <a class="app-brand" href="{{ route('dashboard') }}">
                        <span class="logo-mark">P</span>
                        <span>
                            <span class="logo-text is-dark">PropMgr</span>
                            <span class="eyebrow-text is-dark">Rental operations control</span>
                        </span>
                    </a>

                    <button class="app-sidebar-close" type="button" data-app-nav-toggle aria-label="Close menu">Close</button>
                </div>

                <nav class="app-sidebar-nav" aria-label="Primary navigation">
                    <p class="nav-section">Workspace</p>

                    @foreach ($appNavigation as $item)
                        <a class="app-nav-link{{ $item['active'] ? ' is-active' : '' }}" href="{{ route($item['route']) }}">
                            <span class="app-nav-label">
                                @include('partials.app-icon', ['icon' => $item['icon']])
                                <span>{{ $item['label'] }}</span>
                            </span>
                            @if ($item['badge'])
                                <span class="nav-chip">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="app-main">
                <header class="app-topbar">
                    <div class="app-topbar-meta">
                        <span class="app-topbar-workspace">{{ $isSuperAdmin ? 'Super Admin Workspace' : ($isManager ? 'Manager Workspace' : ($isTenant ? 'Tenant Portal' : 'Workspace')) }}</span>
                        <span class="app-topbar-separator">/</span>
                        <span class="app-topbar-current">{{ str($title ?? 'Dashboard | PropMgr')->before(' | ') }}</span>
                    </div>

                    <div class="app-topbar-actions">
                        <button class="btn btn-ghost btn-sm app-nav-toggle" type="button" data-app-nav-toggle>Menu</button>
                        @if ($authUser)
                            <details class="app-profile-menu">
                                <summary class="app-topbar-profile" aria-label="Open account menu">
                                    <div class="id-avatar">{{ str($authUser->name)->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('') }}</div>
                                    <div>
                                        <p class="app-topbar-profile-name">{{ $authUser->name }}</p>
                                        <p class="app-topbar-profile-role">{{ $authUser->roleSummary() }}</p>
                                    </div>
                                    <span class="app-profile-status">{{ $authUser->hasEnabledTwoFactorAuthentication() ? '2FA active' : '2FA pending' }}</span>
                                </summary>

                                <div class="app-profile-dropdown">
                                    <a class="app-profile-link" href="{{ route('settings.security') }}">@include('partials.app-icon', ['icon' => 'security'])<span>Security settings</span></a>
                                    @if ($isSuperAdmin)
                                        <a class="app-profile-link" href="{{ route('admin.security.two-factor.index') }}">@include('partials.app-icon', ['icon' => 'shield'])<span>2FA oversight</span></a>
                                    @endif
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="app-profile-link app-profile-link-danger" type="submit">@include('partials.app-icon', ['icon' => 'logout'])<span>Sign out</span></button>
                                    </form>
                                </div>
                            </details>
                        @endif
                    </div>
                </header>

                <main class="app-content">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>