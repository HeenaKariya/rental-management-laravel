@extends('layouts.app', ['title' => 'Units | PropMgr'])

@section('content')
    @php
        $visibleUnitCount = $units->count();
        $vacantUnitCount = $units->where('occupancy_status', 'vacant')->count();
        $occupiedUnitCount = $units->where('occupancy_status', 'occupied')->count();
        $propertyCoverage = $units->pluck('property_id')->unique()->count();
    @endphp

    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Unit workspace</p>
                        <h1 class="page-title">Track inventory and occupancy</h1>
                        <p class="page-description">Manage unit inventory on top of the property scope already enforced in the workspace.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('dashboard') }}">Dashboard</a>
                        @can('create', App\Models\Unit::class)
                            <a class="btn btn-solid" href="{{ route('units.create') }}">Add unit</a>
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
                        <p class="stat-label">Visible units</p>
                        <h2 class="stat-value">{{ $visibleUnitCount }}</h2>
                        <p class="stat-meta"><span>{{ $propertyCoverage }} properties covered</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Vacant</p>
                        <h2 class="stat-value">{{ $vacantUnitCount }}</h2>
                        <p class="stat-meta"><span>ready for tenancy flow</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Occupied</p>
                        <h2 class="stat-value">{{ $occupiedUnitCount }}</h2>
                        <p class="stat-meta"><span>active occupancy</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Statuses</p>
                        <h2 class="stat-value">{{ count($occupancyOptions) }}</h2>
                        <p class="stat-meta"><span>unit lifecycle states</span></p>
                    </article>
                </section>

                <article class="form-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Inventory filters</p>
                            <h3 class="dashboard-panel-title">Narrow the unit register</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('units.index') }}">
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Property</span>
                                <select class="field-input" name="property_id">
                                    <option value="">All visible properties</option>
                                    @foreach ($propertyOptions as $propertyOption)
                                        <option value="{{ $propertyOption->id }}" @selected((int) ($filters['property_id'] ?? 0) === $propertyOption->id)>{{ $propertyOption->title }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="field-group">
                                <span class="field-label">Occupancy status</span>
                                <select class="field-input" name="occupancy_status">
                                    <option value="">All statuses</option>
                                    @foreach ($occupancyOptions as $occupancyOption)
                                        <option value="{{ $occupancyOption }}" @selected(($filters['occupancy_status'] ?? null) === $occupancyOption)>{{ str($occupancyOption)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-solid" type="submit">Apply filters</button>
                            <a class="btn btn-ghost" href="{{ route('units.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Unit register</p>
                            <h3 class="dashboard-panel-title">Current inventory</h3>
                        </div>
                        <span class="badge badge-outline">{{ $units->count() }} total</span>
                    </div>

                    @if ($units->isEmpty())
                        <p class="security-empty">No units are visible for the current assignment scope or filter set.</p>
                    @else
                        <div class="data-table-card">
                            <table class="data-table js-data-table" data-page-size="10" data-empty-message="No units matched the current filters.">
                                <thead>
                                    <tr>
                                        <th scope="col" data-sortable="false">Row</th>
                                        <th scope="col">Unit</th>
                                        <th scope="col">Property</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Beds / Baths</th>
                                        <th scope="col">Area</th>
                                        <th scope="col" data-sortable="false">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($units as $unit)
                                        <tr>
                                            <td data-row-number>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="data-table-primary">{{ $unit->unit_number }}</div>
                                                <div class="data-table-secondary">Floor {{ $unit->floor ?: 'N/A' }}</div>
                                            </td>
                                            <td>{{ $unit->property->title }}</td>
                                            <td><span class="badge badge-violet compact-badge">{{ str($unit->occupancy_status)->replace('_', ' ')->title() }}</span></td>
                                            <td>{{ $unit->bedrooms ?? 0 }} / {{ $unit->bathrooms ?? 0 }}</td>
                                            <td>{{ $unit->area ? rtrim(rtrim(number_format((float) $unit->area, 2, '.', ''), '0'), '.') : 'N/A' }} {{ $unit->area_unit }}</td>
                                            <td><a class="btn btn-ghost btn-sm" href="{{ route('units.show', $unit) }}">Open</a></td>
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