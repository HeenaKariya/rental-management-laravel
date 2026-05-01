@extends('layouts.app', ['title' => 'Deposits | PropMgr'])

@section('content')
    @php
        $visibleDepositCount = $deposits->count();
        $openDepositCount = $deposits->where('status', 'open')->count();
        $settledDepositCount = $deposits->where('status', 'settled')->count();
        $heldBalance = $deposits->sum(fn ($deposit) => (float) $deposit->current_balance);
    @endphp

    <div class="">
        <div class="py-2">
            <div class="d-flex flex-column gap-3">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Deposit workspace</p>
                        <h1 class="page-title">Track deposit balances and adjustments</h1>
                        <p class="page-description">Manage deposit accounts as lease-linked sub-ledgers with reconciled balances.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                        @can('create', App\Models\LeaseDeposit::class)
                            <a class="btn btn-primary" href="{{ route('deposits.create') }}">Open deposit</a>
                        @endcan
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Visible deposits</p>
                            <h2 class="stat-value">{{ $visibleDepositCount }}</h2>
                            <p class="stat-meta"><span>lease-linked accounts</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Open</p>
                            <h2 class="stat-value">{{ $openDepositCount }}</h2>
                            <p class="stat-meta"><span>active balances</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Settled</p>
                            <h2 class="stat-value">{{ $settledDepositCount }}</h2>
                            <p class="stat-meta"><span>fully cleared accounts</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Held balance</p>
                            <h2 class="stat-value">{{ number_format($heldBalance, 2) }}</h2>
                            <p class="stat-meta"><span>reconciled ledger balance</span></p>
                        </article>
                    </div>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
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
                            <button class="btn btn-primary" type="submit">Apply filters</button>
                            <a class="btn btn-outline-secondary" href="{{ route('deposits.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                <article class="card border-0 shadow-sm dashboard-panel">
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
                        <div class="">
                            <table class="data-table js-data-table table w-100" data-page-size="10" data-empty-message="No deposit accounts matched the current filters.">
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
                                            <td><a class="btn btn-outline-secondary btn-sm" href="{{ route('deposits.show', $deposit) }}">Open</a></td>
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