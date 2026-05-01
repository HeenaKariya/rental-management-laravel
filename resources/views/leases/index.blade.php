@extends('layouts.app', ['title' => 'Leases | PropMgr'])

@section('content')
    @php
        $visibleLeaseCount = $leases->count();
        $activeLeaseCount = $leases->where('status', 'active')->count();
        $renewedLeaseCount = $leases->where('status', 'renewed')->count();
        $unitCoverage = $leases->pluck('unit_id')->unique()->count();
    @endphp

    <div class="">
        <div class="py-2">
            <div class="d-flex flex-column gap-3">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Lease workspace</p>
                        <h1 class="page-title">Track lease lifecycle and renewals</h1>
                        <p class="page-description">Manage active leases, successor renewals, and the unit-level occupancy boundary from one workspace.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                        @can('create', App\Models\Lease::class)
                            <a class="btn btn-primary" href="{{ route('leases.create') }}">Add lease</a>
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
                            <p class="stat-label">Visible leases</p>
                            <h2 class="stat-value">{{ $visibleLeaseCount }}</h2>
                            <p class="stat-meta"><span>{{ $unitCoverage }} units covered</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Active</p>
                            <h2 class="stat-value">{{ $activeLeaseCount }}</h2>
                            <p class="stat-meta"><span>current billing boundary</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Renewed</p>
                            <h2 class="stat-value">{{ $renewedLeaseCount }}</h2>
                            <p class="stat-meta"><span>historical predecessors</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Statuses</p>
                            <h2 class="stat-value">{{ count($statusOptions) }}</h2>
                            <p class="stat-meta"><span>lease lifecycle states</span></p>
                        </article>
                    </div>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Lease filters</p>
                            <h3 class="dashboard-panel-title">Narrow the lease register</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('leases.index') }}">
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Unit</span>
                                <select class="field-input" name="unit_id">
                                    <option value="">All visible units</option>
                                    @foreach ($leaseUnits as $leaseUnit)
                                        <option value="{{ $leaseUnit->id }}" @selected((int) ($filters['unit_id'] ?? 0) === $leaseUnit->id)>{{ $leaseUnit->property->title }} · {{ $leaseUnit->unit_number }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="field-group">
                                <span class="field-label">Status</span>
                                <select class="field-input" name="status">
                                    <option value="">All statuses</option>
                                    @foreach ($statusOptions as $statusOption)
                                        <option value="{{ $statusOption }}" @selected(($filters['status'] ?? null) === $statusOption)>{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-primary" type="submit">Apply filters</button>
                            <a class="btn btn-outline-secondary" href="{{ route('leases.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Lease register</p>
                            <h3 class="dashboard-panel-title">Current and prior leases</h3>
                        </div>
                        <span class="badge badge-outline">{{ $leases->count() }} total</span>
                    </div>

                    @if ($leases->isEmpty())
                        <p class="security-empty">No leases are visible for the current scope or filters.</p>
                    @else
                        <div class="">
                            <table class="data-table js-data-table table w-100" data-page-size="10" data-empty-message="No leases matched the current filters.">
                                <thead>
                                    <tr>
                                        <th scope="col" data-sortable="false">Row</th>
                                        <th scope="col">Lease</th>
                                        <th scope="col">Tenant</th>
                                        <th scope="col">Unit</th>
                                        <th scope="col">Dates</th>
                                        <th scope="col">Rent</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" data-sortable="false">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($leases as $lease)
                                        <tr>
                                            <td data-row-number>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="data-table-primary">{{ $lease->lease_number }}</div>
                                                <div class="data-table-secondary">Billing day {{ $lease->billing_day }}</div>
                                            </td>
                                            <td>{{ $lease->tenant->full_name }}</td>
                                            <td>{{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}</td>
                                            <td>{{ $lease->start_on->format('M j, Y') }} - {{ $lease->end_on->format('M j, Y') }}</td>
                                            <td>{{ number_format((float) $lease->rent_amount, 2) }}</td>
                                            <td><span class="badge badge-violet compact-badge">{{ str($lease->status)->replace('_', ' ')->title() }}</span></td>
                                            <td><a class="btn btn-outline-secondary btn-sm" href="{{ route('leases.show', $lease) }}">Open</a></td>
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