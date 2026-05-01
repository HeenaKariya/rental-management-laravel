@extends('layouts.app', ['title' => '2FA Oversight | PropMgr'])

@section('content')
    <div class="ui-shell oversight-page">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header">
                    <div>
                        <p class="page-kicker">Super Admin panel</p>
                        <h1 class="page-title">Two-factor oversight</h1>
                        <p class="page-description">Monitor two-factor adoption and recent auth activity.</p>
                    </div>

                    <div class="page-actions d-flex flex-wrap gap-2">
                        <span class="badge badge-ink">{{ $users->count() }} monitored accounts</span>
                        <a class="btn btn-violet btn-sm" href="{{ route('settings.security') }}">My security</a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Back to dashboard</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
                @endif

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Confirmed</p>
                        <h2 class="stat-value">{{ $summary['confirmed'] }}</h2>
                        <p class="stat-meta"><span>fully confirmed enrollment</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Pending</p>
                        <h2 class="stat-value">{{ $summary['pending'] }}</h2>
                        <p class="stat-meta"><span>started but not confirmed</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Not enabled</p>
                        <h2 class="stat-value">{{ $summary['notEnabled'] }}</h2>
                        <p class="stat-meta"><span>still need enrollment</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Soft Locked</p>
                        <h2 class="stat-value">{{ $summary['softLocked'] }}</h2>
                        <p class="stat-meta"><span>temporary lock flow</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <p class="row-label mb-0">Account status</p>
                                <h3 class="dashboard-panel-title mb-0">Monitored users</h3>
                            </div>

                            <div class="oversight-table-card">
                                <table id="oversight-users-table" class="table w-100 data-table data-table-compact js-jquery-datatable">
                                    <thead>
                                        <tr>
                                            <th scope="col" class="dt-control" data-sortable="false"></th>
                                            <th scope="col" data-sortable="false">Row</th>
                                            <th scope="col">Row ID</th>
                                            <th scope="col">User</th>
                                            <th scope="col">2FA status</th>
                                            <th scope="col">Last event</th>
                                            <th scope="col">Updated</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($users as $user)
                                            <tr>
                                                <td class="dt-control"></td>
                                                <td data-row-number>{{ $loop->iteration }}</td>
                                                <td>#{{ $user->id }}</td>
                                                <td>
                                                    <div class="data-table-primary">{{ $user->name }}</div>
                                                    <div class="data-table-secondary">{{ $user->roleSummary() }} · {{ $user->email }}</div>
                                                    @if ($user->isSoftLocked())
                                                        <div class="data-table-secondary">Temporarily locked</div>
                                                    @elseif ($user->isHardLocked())
                                                        <div class="data-table-secondary">Super Admin reset required</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="badge-strip">
                                                        <span class="badge {{ $user->twoFactorStatusBadgeClass() }} compact-badge">{{ $user->twoFactorStatus() }}</span>
                                                        <span class="badge {{ $user->authLockStatusBadgeClass() }} compact-badge">{{ $user->authLockStatus() }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($user->latestAuthAuditLog)
                                                        <div class="data-table-primary">{{ $user->latestAuthAuditLog->label() }}</div>
                                                        @if ($user->latestAuthAuditLog->summary())
                                                            <div class="data-table-secondary">{{ $user->latestAuthAuditLog->summary() }}</div>
                                                        @endif
                                                    @else
                                                        <div class="data-table-primary">No recorded auth activity</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($user->latestAuthAuditLog)
                                                        <div class="data-table-primary">{{ $user->latestAuthAuditLog->occurred_at?->timezone('Asia/Kolkata')->format('M j, Y') }}</div>
                                                        <div class="data-table-secondary">{{ $user->latestAuthAuditLog->occurred_at?->timezone('Asia/Kolkata')->format('g:i A') }} IST</div>
                                                    @else
                                                        <div class="data-table-primary">Never</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        @if ($user->isAuthLocked())
                                                            <form method="POST" action="{{ route('admin.security.two-factor.release-lock', $user) }}">
                                                                @csrf
                                                                <button class="btn btn-ghost btn-sm" type="submit">Release lock</button>
                                                            </form>
                                                        @endif

                                                        @if ($user->two_factor_secret !== null)
                                                            <form method="POST" action="{{ route('admin.security.two-factor.reset', $user) }}">
                                                                @csrf
                                                                <button class="btn btn-violet btn-sm" type="submit">Reset 2FA</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <aside class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <p class="row-label mb-0">Recent events</p>
                                <h3 class="dashboard-panel-title mb-0">Platform authentication activity</h3>
                            </div>

                            <div class="security-log-list">
                                @forelse ($auditLogs as $auditLog)
                                    <article class="security-log-item">
                                        <div class="security-log-head">
                                            <span class="badge {{ $auditLog->badgeClass() }}">{{ $auditLog->label() }}</span>
                                            <span class="security-log-meta">{{ $auditLog->occurred_at?->timezone('Asia/Kolkata')->format('M j, Y g:i A') }} IST</span>
                                        </div>

                                        <p class="oversight-log-copy">
                                            {{ $auditLog->user?->name ?? 'Unknown user' }}
                                            @if ($auditLog->user)
                                                · {{ $auditLog->user->roleSummary() }}
                                            @endif
                                        </p>

                                        @if ($auditLog->summary())
                                            <p class="security-log-meta">{{ $auditLog->summary() }}</p>
                                        @endif
                                    </article>
                                @empty
                                    <p class="security-empty">No authentication events are available yet.</p>
                                @endforelse
                            </div>
                        </aside>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection