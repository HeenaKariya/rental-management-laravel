@extends('layouts.app', ['title' => 'Tenants | PropMgr'])

@section('content')
    @php
        $visibleTenantCount = $tenants->count();
        $activeTenantCount = $tenants->where('status', 'active')->count();
        $verifiedKycCount = $tenants->where('kyc_status', 'verified')->count();
        $unitCoverage = $tenants->pluck('unit_id')->unique()->count();
    @endphp

    <div class="">
        <div class="py-2">
            <div class="d-flex flex-column gap-3">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Tenant workspace</p>
                        <h1 class="page-title">Manage tenants and KYC</h1>
                        <p class="page-description">Track tenant lifecycle and supporting KYC documents inside the same property-scoped workspace.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                        @can('create', App\Models\Tenant::class)
                            <a class="btn btn-primary" href="{{ route('tenants.create') }}">Add tenant</a>
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
                            <p class="stat-label">Visible tenants</p>
                            <h2 class="stat-value">{{ $visibleTenantCount }}</h2>
                            <p class="stat-meta"><span>{{ $unitCoverage }} units covered</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Active</p>
                            <h2 class="stat-value">{{ $activeTenantCount }}</h2>
                            <p class="stat-meta"><span>current tenant records</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">KYC verified</p>
                            <h2 class="stat-value">{{ $verifiedKycCount }}</h2>
                            <p class="stat-meta"><span>ready for lease workflow</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">KYC states</p>
                            <h2 class="stat-value">{{ count($kycStatusOptions) }}</h2>
                            <p class="stat-meta"><span>review statuses</span></p>
                        </article>
                    </div>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Tenant filters</p>
                            <h3 class="dashboard-panel-title">Narrow the register</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('tenants.index') }}">
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Unit</span>
                                <select class="field-input" name="unit_id">
                                    <option value="">All visible units</option>
                                    @foreach ($tenantUnits as $tenantUnit)
                                        <option value="{{ $tenantUnit->id }}" @selected((int) ($filters['unit_id'] ?? 0) === $tenantUnit->id)>{{ $tenantUnit->property->title }} · {{ $tenantUnit->unit_number }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="field-group">
                                <span class="field-label">Tenant status</span>
                                <select class="field-input" name="status">
                                    <option value="">All statuses</option>
                                    @foreach ($statusOptions as $statusOption)
                                        <option value="{{ $statusOption }}" @selected(($filters['status'] ?? null) === $statusOption)>{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <label class="field-group">
                            <span class="field-label">KYC status</span>
                            <select class="field-input" name="kyc_status">
                                <option value="">All KYC states</option>
                                @foreach ($kycStatusOptions as $kycStatusOption)
                                    <option value="{{ $kycStatusOption }}" @selected(($filters['kyc_status'] ?? null) === $kycStatusOption)>{{ str($kycStatusOption)->replace('_', ' ')->title() }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-primary" type="submit">Apply filters</button>
                            <a class="btn btn-outline-secondary" href="{{ route('tenants.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Tenant register</p>
                            <h3 class="dashboard-panel-title">Current tenancy contacts</h3>
                        </div>
                        <span class="badge badge-outline">{{ $tenants->count() }} total</span>
                    </div>

                    @if ($tenants->isEmpty())
                        <p class="security-empty">No tenants are visible for the current assignment scope or filters.</p>
                    @else
                        <div class="">
                            <table class="data-table js-data-table table w-100" data-page-size="10" data-empty-message="No tenants matched the current filters.">
                                <thead>
                                    <tr>
                                        <th scope="col" data-sortable="false">Row</th>
                                        <th scope="col">Tenant</th>
                                        <th scope="col">Unit</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">KYC</th>
                                        <th scope="col">Docs</th>
                                        <th scope="col" data-sortable="false">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tenants as $tenant)
                                        <tr>
                                            <td data-row-number>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="data-table-primary">{{ $tenant->full_name }}</div>
                                                <div class="data-table-secondary">{{ $tenant->email ?: ($tenant->phone ?: 'No contact info') }}</div>
                                            </td>
                                            <td>{{ $tenant->unit->property->title }} · {{ $tenant->unit->unit_number }}</td>
                                            <td><span class="badge badge-violet compact-badge">{{ str($tenant->status)->replace('_', ' ')->title() }}</span></td>
                                            <td><span class="badge badge-outline compact-badge">{{ str($tenant->kyc_status)->replace('_', ' ')->title() }}</span></td>
                                            <td>{{ $tenant->documents->count() }}</td>
                                            <td><a class="btn btn-outline-secondary btn-sm" href="{{ route('tenants.show', $tenant) }}">Open</a></td>
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