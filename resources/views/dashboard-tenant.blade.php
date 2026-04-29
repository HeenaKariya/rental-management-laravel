@extends('layouts.app', ['title' => 'Tenant Portal | PropMgr'])

@section('content')
    <div class="dashboard-stack">
        <section class="page-header card-soft">
            <div>
                <p class="page-kicker">Tenant portal</p>
                <h1 class="page-title">Your tenancy workspace</h1>
                <p class="page-description">
                    @if ($tenant)
                        View your tenant profile, active leases, deposit balances, and recent security activity in one place.
                    @else
                        Your account is active, but no tenant record is linked yet. Contact your property manager to complete the tenancy profile.
                    @endif
                </p>
            </div>

            <div class="page-actions">
                @if ($tenant)
                    <a class="btn btn-solid" href="{{ route('tenants.show', $tenant) }}">Open profile</a>
                @endif
                <a class="btn btn-ghost" href="{{ route('settings.security') }}">Security settings</a>
            </div>
        </section>

        <section class="stat-grid dashboard-stat-grid">
            <article class="stat-card">
                <p class="stat-label">Linked unit</p>
                <h2 class="stat-value">{{ $tenant?->unit?->unit_number ?: 'Pending' }}</h2>
                <p class="stat-meta"><span>{{ $tenant?->unit?->property?->title ?: 'Waiting for tenancy assignment' }}</span></p>
            </article>
            <article class="stat-card">
                <p class="stat-label">Leases</p>
                <h2 class="stat-value">{{ $summary['leases'] }}</h2>
                <p class="stat-meta"><span>{{ $summary['activeLeases'] }} active</span></p>
            </article>
            <article class="stat-card">
                <p class="stat-label">KYC documents</p>
                <h2 class="stat-value">{{ $summary['documents'] }}</h2>
                <p class="stat-meta"><span>{{ $tenant?->kyc_status ? str($tenant->kyc_status)->replace('_', ' ')->title() : 'not started' }}</span></p>
            </article>
            <article class="stat-card">
                <p class="stat-label">Deposit balance</p>
                <h2 class="stat-value">{{ number_format($summary['depositBalance'], 2) }}</h2>
                <p class="stat-meta"><span>visible deposit accounts</span></p>
            </article>
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-column-wide">
                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Profile overview</p>
                            <h3 class="dashboard-panel-title">Tenant record</h3>
                        </div>
                        @if ($tenant)
                            <a class="btn btn-ghost btn-sm" href="{{ route('tenants.show', $tenant) }}">Open detail</a>
                        @endif
                    </div>

                    @if (! $tenant)
                        <p class="security-empty">No tenant record is linked to this account yet.</p>
                    @else
                        <div class="table-head">
                            <span>Name</span>
                            <span>Contact</span>
                            <span>Status</span>
                            <span>KYC</span>
                        </div>
                        <a class="table-row" href="{{ route('tenants.show', $tenant) }}" style="text-decoration: none; color: inherit;">
                            <div>
                                <div class="tenant-name">{{ $tenant->full_name }}</div>
                                <div class="tenant-unit">{{ $tenant->unit->property->title }} · {{ $tenant->unit->unit_number }}</div>
                            </div>
                            <div class="muted-text">{{ $tenant->email ?: ($tenant->phone ?: 'Not provided') }}</div>
                            <span class="badge badge-violet compact-badge">{{ str($tenant->status)->replace('_', ' ')->title() }}</span>
                            <div class="muted-text">{{ str($tenant->kyc_status)->replace('_', ' ')->title() }}</div>
                        </a>
                    @endif
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Lease register</p>
                            <h3 class="dashboard-panel-title">Your lease records</h3>
                        </div>
                    </div>

                    @if ($leases->isEmpty())
                        <p class="security-empty">No lease records are linked to this account yet.</p>
                    @else
                        <div class="table-head">
                            <span>Lease</span>
                            <span>Rent</span>
                            <span>Dates</span>
                            <span>Status</span>
                        </div>
                        @foreach ($leases as $lease)
                            <a class="table-row" href="{{ route('leases.show', $lease) }}" style="text-decoration: none; color: inherit;">
                                <div>
                                    <div class="tenant-name">{{ $lease->lease_number }}</div>
                                    <div class="tenant-unit">{{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}</div>
                                </div>
                                <div class="muted-text">{{ number_format((float) $lease->rent_amount, 2) }}</div>
                                <div class="muted-text">{{ $lease->start_on->format('M j, Y') }} to {{ $lease->end_on->format('M j, Y') }}</div>
                                <span class="badge badge-violet compact-badge">{{ str($lease->status)->replace('_', ' ')->title() }}</span>
                            </a>
                        @endforeach
                    @endif
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Deposit accounts</p>
                            <h3 class="dashboard-panel-title">Visible deposit balances</h3>
                        </div>
                    </div>

                    @if ($deposits->isEmpty())
                        <p class="security-empty">No deposit accounts are linked to your tenancy records.</p>
                    @else
                        <div class="table-head">
                            <span>Lease</span>
                            <span>Expected</span>
                            <span>Balance</span>
                            <span>Status</span>
                        </div>
                        @foreach ($deposits as $deposit)
                            <a class="table-row" href="{{ route('deposits.show', $deposit) }}" style="text-decoration: none; color: inherit;">
                                <div>
                                    <div class="tenant-name">{{ $deposit->lease->lease_number }}</div>
                                    <div class="tenant-unit">{{ $deposit->lease->unit->property->title }} · {{ $deposit->lease->unit->unit_number }}</div>
                                </div>
                                <div class="muted-text">{{ number_format((float) $deposit->expected_amount, 2) }}</div>
                                <div class="muted-text">{{ number_format((float) $deposit->current_balance, 2) }}</div>
                                <span class="badge badge-violet compact-badge">{{ str($deposit->status)->replace('_', ' ')->title() }}</span>
                            </a>
                        @endforeach
                    @endif
                </article>
            </div>

            <div class="dashboard-column-side">
                <article class="feed-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Security activity</p>
                            <h3 class="dashboard-panel-title">Recent auth events</h3>
                        </div>
                        <a class="btn btn-ghost btn-sm" href="{{ route('settings.security') }}">Open security</a>
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
                                <p class="feed-meta">{{ $event->occurred_at?->format('M j, g:i A') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="security-empty">No authentication events recorded yet.</p>
                    @endforelse
                </article>

                <article class="quick-grid dashboard-quick-grid">
                    <article class="quick-card">
                        <div class="quick-icon is-green"></div>
                        <h3>Profile record</h3>
                        <p>Review your tenant identity, KYC status, and linked unit assignment.</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-violet"></div>
                        <h3>Lease history</h3>
                        <p>Track your active and previous lease terms without staff edit controls.</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-sky"></div>
                        <h3>Deposit visibility</h3>
                        <p>See the held deposit balance and any deductions, refunds, or forfeitures.</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-gold"></div>
                        <h3>Account security</h3>
                        <p>Keep your sign-in methods and recovery posture current from the same portal.</p>
                    </article>
                </article>
            </div>
        </section>
    </div>
@endsection