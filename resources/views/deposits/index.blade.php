@extends('layouts.app', ['title' => 'Deposits | PropMgr'])

@section('content')
    @php
        $visibleDepositCount = $deposits->count();
        $openDepositCount = $deposits->where('status', 'open')->count();
        $settledDepositCount = $deposits->where('status', 'settled')->count();
        $heldBalance = $deposits->sum(fn ($deposit) => (float) $deposit->current_balance);
    @endphp

    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Deposit workspace</p>
                        <h1 class="page-title">Track deposit balances and adjustments</h1>
                        <p class="page-description">Manage deposit accounts as lease-linked sub-ledgers with reconciled balances.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('dashboard') }}">Dashboard</a>
                        @can('create', App\Models\LeaseDeposit::class)
                            <a class="btn btn-solid" href="{{ route('deposits.create') }}">Open deposit</a>
                        @endcan
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Visible deposits</p>
                        <h2 class="stat-value">{{ $visibleDepositCount }}</h2>
                        <p class="stat-meta"><span>lease-linked accounts</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Open</p>
                        <h2 class="stat-value">{{ $openDepositCount }}</h2>
                        <p class="stat-meta"><span>active balances</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Settled</p>
                        <h2 class="stat-value">{{ $settledDepositCount }}</h2>
                        <p class="stat-meta"><span>fully cleared accounts</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Held balance</p>
                        <h2 class="stat-value">{{ number_format($heldBalance, 2) }}</h2>
                        <p class="stat-meta"><span>reconciled ledger balance</span></p>
                    </article>
                </section>

                <article class="form-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Deposit filters</p>
                            <h3 class="dashboard-panel-title">Narrow deposit accounts</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('deposits.index') }}">
                        <label class="field-group">
                            <span class="field-label">Status</span>
                            <select class="field-input" name="status">
                                <option value="">All statuses</option>
                                @foreach ($statusOptions as $statusOption)
                                    <option value="{{ $statusOption }}" @selected(($filters['status'] ?? null) === $statusOption)>{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-solid" type="submit">Apply filters</button>
                            <a class="btn btn-ghost" href="{{ route('deposits.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Deposit register</p>
                            <h3 class="dashboard-panel-title">Lease deposit accounts</h3>
                        </div>
                        <span class="badge badge-outline">{{ $deposits->count() }} total</span>
                    </div>

                    @if ($deposits->isEmpty())
                        <p class="security-empty">No deposit accounts are visible for the current scope or filters.</p>
                    @else
                        <div class="data-table-card">
                            <table class="data-table js-data-table" data-page-size="10" data-empty-message="No deposit accounts matched the current filters.">
                                <thead>
                                    <tr>
                                        <th scope="col" data-sortable="false">Row</th>
                                        <th scope="col">Lease</th>
                                        <th scope="col">Tenant</th>
                                        <th scope="col">Expected</th>
                                        <th scope="col">Balance</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" data-sortable="false">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($deposits as $deposit)
                                        <tr>
                                            <td data-row-number>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="data-table-primary">{{ $deposit->lease->lease_number }}</div>
                                                <div class="data-table-secondary">{{ $deposit->lease->unit->property->title }} · {{ $deposit->lease->unit->unit_number }}</div>
                                            </td>
                                            <td>{{ $deposit->lease->tenant->full_name }}</td>
                                            <td>{{ number_format((float) $deposit->expected_amount, 2) }}</td>
                                            <td>{{ number_format((float) $deposit->current_balance, 2) }}</td>
                                            <td><span class="badge badge-violet compact-badge">{{ str($deposit->status)->replace('_', ' ')->title() }}</span></td>
                                            <td><a class="btn btn-ghost btn-sm" href="{{ route('deposits.show', $deposit) }}">Open</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </article>
            </div>
        </div>
    </div>
@endsection