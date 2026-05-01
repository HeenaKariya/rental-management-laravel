@extends('layouts.app', ['title' => 'Unit Detail | PropMgr'])

@section('content')
    <div class=" property-workspace">
        <div class="py-2">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Unit detail</p>
                        <h1 class="page-title">{{ $unit->unit_number }}</h1>
                        <p class="page-description">Unit inventory and occupancy details scoped through {{ $unit->property->title }}.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('units.index') }}">Back to units</a>
                        @can('create', App\Models\Lease::class)
                            <a class="btn btn-outline-secondary" href="{{ route('leases.create', ['unit_id' => $unit->id]) }}">Start new lease</a>
                        @endcan
                        @can('update', $unit)
                            <a class="btn btn-primary" href="{{ route('units.edit', $unit) }}">Edit unit</a>
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
                            <p class="stat-label">Property</p>
                            <h2 class="stat-value">#{{ $unit->property_id }}</h2>
                            <p class="stat-meta"><span>{{ $unit->property->title }}</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Status</p>
                            <h2 class="stat-value">{{ str($unit->occupancy_status)->replace('_', ' ')->title() }}</h2>
                            <p class="stat-meta"><span>{{ $unit->vacant_since ? 'Vacant since '.$unit->vacant_since->format('M j, Y') : 'Occupancy tracked' }}</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Bedrooms</p>
                            <h2 class="stat-value">{{ $unit->bedrooms ?? 0 }}</h2>
                            <p class="stat-meta"><span>unit layout</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Area</p>
                            <h2 class="stat-value">{{ $unit->area ? rtrim(rtrim(number_format((float) $unit->area, 2, '.', ''), '0'), '.') : 'N/A' }}</h2>
                            <p class="stat-meta"><span>{{ $unit->area_unit }}</span></p>
                        </article>
                    </div>
                </section>

                <section class="row g-3">
                    <div class="col-12 col-xl-8 d-flex flex-column gap-3">
                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Inventory profile</p>
                                    <h3 class="dashboard-panel-title">Current unit details</h3>
                                </div>
                            </div>

                            <div class="two-up-grid">
                                <div class="field-group">
                                    <span class="field-label">Property</span>
                                    <div class="field-input">{{ $unit->property->title }}</div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Floor</span>
                                    <div class="field-input">{{ $unit->floor ?: 'Not specified' }}</div>
                                </div>
                            </div>

                            <div class="two-up-grid">
                                <div class="field-group">
                                    <span class="field-label">Bathrooms</span>
                                    <div class="field-input">{{ $unit->bathrooms ?? 0 }}</div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Vacant since</span>
                                    <div class="field-input">{{ $unit->vacant_since?->format('M j, Y') ?: 'N/A' }}</div>
                                </div>
                            </div>

                            <div class="field-group">
                                <span class="field-label">Notes</span>
                                <div class="field-input">{{ $unit->notes ?: 'No notes recorded for this unit yet.' }}</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Next module</p>
                                    <h3 class="dashboard-panel-title">Ready for tenancy</h3>
                                </div>
                            </div>

                            <p class="security-meta">This unit foundation is now ready for tenant onboarding, KYC records, and lease lifecycle work in the next Phase 3 slices.</p>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection