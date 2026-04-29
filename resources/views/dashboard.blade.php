@extends('layouts.app', ['title' => 'Dashboard | PropMgr'])

@section('content')
    <div class="dashboard-stack">
        @if ($quickActions->isNotEmpty())
            <section class="page-header card-soft">
                <div>
                    <p class="page-kicker">Workspace overview</p>
                    <h1 class="page-title">Operations dashboard</h1>
                    <p class="page-description">Move between the current property, security, and onboarding workstreams from one shared control surface.</p>
                </div>

                <div class="page-actions">
                    @foreach ($quickActions as $action)
                        <a class="btn btn-{{ $action['style'] }}" href="{{ $action['route'] }}">{{ $action['label'] }}</a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="stat-grid dashboard-stat-grid">
            <article class="stat-card">
                <p class="stat-label">Visible properties</p>
                <h2 class="stat-value">{{ $summary['properties'] }}</h2>
                <p class="stat-meta">
                    <span class="stat-pill positive">{{ $summary['activeProperties'] }} active</span>
                    <span>{{ $summary['draftProperties'] }} draft</span>
                </p>
            </article>
            <article class="stat-card">
                <p class="stat-label">Manager coverage</p>
                <h2 class="stat-value">{{ $summary['managerAssignments'] }}</h2>
                <p class="stat-meta"><span>{{ $user->hasRole('super_admin') ? 'manager accounts' : 'assigned properties' }}</span></p>
            </article>
            <article class="stat-card">
                <p class="stat-label">Open invitations</p>
                <h2 class="stat-value">{{ $summary['openInvitations'] }}</h2>
                <p class="stat-meta"><span>{{ $user->hasRole('super_admin') ? 'pending acceptance' : 'super admin only' }}</span></p>
            </article>
            <article class="stat-card">
                <p class="stat-label">Auth events · 24h</p>
                <h2 class="stat-value">{{ $summary['recentAuthEvents'] }}</h2>
                <p class="stat-meta"><span>latest monitored activity</span></p>
            </article>
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-column-wide">
                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Property overview</p>
                            <h3 class="dashboard-panel-title">Current portfolio</h3>
                        </div>
                        <a class="btn btn-ghost btn-sm" href="{{ route('properties.index') }}">Open properties</a>
                    </div>

                    @if ($properties->isEmpty())
                        <p class="security-empty">No properties are visible yet. Start by creating the first property record.</p>
                    @else
                        <div class="table-head">
                            <span>Property</span>
                            <span>Type</span>
                            <span>Stage</span>
                            <span>Managers</span>
                        </div>

                        @foreach ($properties as $property)
                            <a class="table-row" href="{{ route('properties.show', $property) }}" style="text-decoration: none; color: inherit;">
                                <div>
                                    <div class="tenant-name">{{ $property->title }}</div>
                                    <div class="tenant-unit">{{ $property->city }}, {{ $property->state }}</div>
                                </div>
                                <div class="muted-text">{{ str($property->type)->replace('_', ' ')->title() }}</div>
                                <span class="badge badge-violet compact-badge">{{ str($property->lifecycle_stage)->replace('_', ' ')->title() }}</span>
                                <div class="muted-text">{{ $property->managers->pluck('name')->implode(', ') ?: 'Unassigned' }}</div>
                            </a>
                        @endforeach
                    @endif
                </article>

                <article class="pending-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Implementation focus</p>
                            <h3 class="dashboard-panel-title">Phase roadmap</h3>
                        </div>
                    </div>

                    <div class="pending-row"><span>Phase 1 security core</span><span class="pending-pill is-green">Done</span></div>
                    <div class="pending-row"><span>Phase 2 property core and manager scoping</span><span class="pending-pill is-gold">Active</span></div>
                    <div class="pending-row"><span>Phase 3 units, tenants, leases, deposits</span><span class="pending-pill is-neutral">Next</span></div>
                    <div class="pending-row"><span>Phase 4 rent ledger and returns</span><span class="pending-pill is-neutral">Queued</span></div>
                </article>
            </div>

            <div class="dashboard-column-side">
                <article class="feed-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Security activity</p>
                            <h3 class="dashboard-panel-title">Recent auth events</h3>
                        </div>
                        @if ($user->hasRole('super_admin'))
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.security.two-factor.index') }}">Oversight</a>
                        @endif
                    </div>

                    @forelse ($authEvents as $event)
                        <div class="feed-item">
                            <div class="feed-rail">
                                <span class="feed-dot is-green"></span>
                                @if (! $loop->last)
                                    <span class="feed-line"></span>
                                @endif
                            </div>
                            <div>
                                <p class="feed-text">{{ $event->label() }}</p>
                                <p class="feed-meta">
                                    {{ $event->user?->name ?? 'Unknown user' }}
                                    @if ($event->user)
                                        · {{ $event->user->roleSummary() }}
                                    @endif
                                    · {{ $event->occurred_at?->format('M j, g:i A') }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="security-empty">No authentication events recorded yet.</p>
                    @endforelse
                </article>

                <article class="quick-grid dashboard-quick-grid">
                    <article class="quick-card">
                        <div class="quick-icon is-green"></div>
                        <h3>Property CRUD</h3>
                        <p>Create, assign, archive, and scope portfolio records.</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-violet"></div>
                        <h3>Role controls</h3>
                        <p>Super Admin sees the full platform, managers see assigned property data only.</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-sky"></div>
                        <h3>Invite-only access</h3>
                        <p>Open invitations and onboarding remain locked behind the Phase 1 auth boundary.</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-gold"></div>
                        <h3>Next modules</h3>
                        <p>Units, tenants, leases, and deposits will attach to this workspace next.</p>
                    </article>
                </article>
            </div>
        </section>
    </div>
@endsection