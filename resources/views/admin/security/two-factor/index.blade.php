@extends('layouts.app', ['title' => '2FA Oversight | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <section class="page-header card-soft">
                <div>
                    <p class="page-kicker">Super Admin panel</p>
                    <h1 class="page-title">Two-factor oversight</h1>
                    <p class="page-description">Monitor adoption, spot lockouts, and recover access issues from one operational panel.</p>
                </div>

                <div class="page-actions">
                    <span class="badge badge-ink">{{ $users->count() }} monitored accounts</span>
                    <a class="btn btn-violet btn-sm" href="{{ route('settings.security') }}">My security</a>
                    <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Back to dashboard</a>
                </div>
            </section>

            @if (session('status'))
                <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
            @endif

            <section class="metric-grid">
                <article class="metric-card">
                    <p class="row-label">Confirmed</p>
                    <p class="metric-value">{{ $summary['confirmed'] }}</p>
                    <p class="metric-copy">Users with fully confirmed authenticator setup.</p>
                </article>
                <article class="metric-card">
                    <p class="row-label">Pending</p>
                    <p class="metric-value">{{ $summary['pending'] }}</p>
                    <p class="metric-copy">Users who started setup but have not confirmed it yet.</p>
                </article>
                <article class="metric-card">
                    <p class="row-label">Not Enabled</p>
                    <p class="metric-value">{{ $summary['notEnabled'] }}</p>
                    <p class="metric-copy">Users who still need two-factor enrollment.</p>
                </article>
                <article class="metric-card">
                    <p class="row-label">Soft Locked</p>
                    <p class="metric-value">{{ $summary['softLocked'] }}</p>
                    <p class="metric-copy">Users currently paused by the temporary lock flow.</p>
                </article>
                <article class="metric-card">
                    <p class="row-label">Hard Locked</p>
                    <p class="metric-value">{{ $summary['hardLocked'] }}</p>
                    <p class="metric-copy">Users who now require Super Admin intervention.</p>
                </article>
            </section>

            <section class="oversight-layout">
                <div class="oversight-stack">
                    <div>
                        <p class="row-label">Account status</p>
                        <div class="table-card">
                            <div class="table-head">
                                <span>User</span>
                                <span>2FA status</span>
                                <span>Last event</span>
                                <span>Updated</span>
                            </div>

                            @foreach ($users as $user)
                                <div class="table-row">
                                    <div class="oversight-user-meta">
                                        <p class="oversight-user-name">{{ $user->name }}</p>
                                        <p class="oversight-user-role">{{ $user->roleSummary() }}</p>
                                        <p class="oversight-user-email">{{ $user->email }}</p>

                                        @if ($user->isAuthLocked() || $user->two_factor_secret !== null)
                                            <div class="oversight-actions">
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
                                        @endif
                                    </div>

                                    <div class="badge-strip">
                                        <span class="badge {{ $user->twoFactorStatusBadgeClass() }} compact-badge">{{ $user->twoFactorStatus() }}</span>
                                        <span class="badge {{ $user->authLockStatusBadgeClass() }} compact-badge">{{ $user->authLockStatus() }}</span>
                                    </div>

                                    <div class="oversight-user-meta">
                                        @if ($user->latestAuthAuditLog)
                                            <p class="oversight-user-name">{{ $user->latestAuthAuditLog->label() }}</p>
                                            @if ($user->latestAuthAuditLog->summary())
                                                <p class="oversight-user-email">{{ $user->latestAuthAuditLog->summary() }}</p>
                                            @endif
                                        @else
                                            <p class="oversight-user-name">No recorded auth activity</p>
                                        @endif
                                    </div>

                                    <div class="oversight-user-meta">
                                        @if ($user->latestAuthAuditLog)
                                            <p class="oversight-user-name">{{ $user->latestAuthAuditLog->occurred_at?->format('M j, Y') }}</p>
                                            <p class="oversight-user-email">{{ $user->latestAuthAuditLog->occurred_at?->format('g:i A') }}</p>
                                        @else
                                            <p class="oversight-user-name">Never</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <aside class="security-card">
                    <div>
                        <p class="row-label">Recent events</p>
                        <h2 class="hero-text" style="margin-top: 0; max-width: none;">Latest platform authentication activity</h2>
                    </div>

                    <div class="security-log-list">
                        @forelse ($auditLogs as $auditLog)
                            <article class="security-log-item">
                                <div class="security-log-head">
                                    <span class="badge {{ $auditLog->badgeClass() }}">{{ $auditLog->label() }}</span>
                                    <span class="security-log-meta">{{ $auditLog->occurred_at?->format('M j, Y g:i A') }}</span>
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
            </section>
        </div>
    </div>
@endsection