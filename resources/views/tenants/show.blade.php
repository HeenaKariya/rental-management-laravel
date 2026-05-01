@extends('layouts.app', ['title' => 'Tenant Detail | PropMgr'])

@section('content')
    <div class=" property-workspace">
        <div class="py-2">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Tenant detail</p>
                        <h1 class="page-title">{{ $tenant->full_name }}</h1>
                        <p class="page-description">Tenant profile and KYC records scoped through {{ $tenant->unit->property->title }} · {{ $tenant->unit->unit_number }}.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ $user?->hasRole('tenant') ? route('dashboard') : route('tenants.index') }}">{{ $user?->hasRole('tenant') ? 'Back to portal' : 'Back to tenants' }}</a>
                        @can('update', $tenant)
                            <a class="btn btn-primary" href="{{ route('tenants.edit', $tenant) }}">Edit tenant</a>
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
                            <p class="stat-label">Unit</p>
                            <h2 class="stat-value">{{ $tenant->unit->unit_number }}</h2>
                            <p class="stat-meta"><span>{{ $tenant->unit->property->title }}</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Tenant status</p>
                            <h2 class="stat-value">{{ str($tenant->status)->replace('_', ' ')->title() }}</h2>
                            <p class="stat-meta"><span>lifecycle state</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">KYC</p>
                            <h2 class="stat-value">{{ str($tenant->kyc_status)->replace('_', ' ')->title() }}</h2>
                            <p class="stat-meta"><span>{{ $tenant->documents->count() }} documents uploaded</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Move-in</p>
                            <h2 class="stat-value">{{ $tenant->move_in_on?->format('M j') ?: 'N/A' }}</h2>
                            <p class="stat-meta"><span>{{ $tenant->move_in_on?->format('Y') ?: 'not scheduled' }}</span></p>
                        </article>
                    </div>
                </section>

                <section class="row g-3">
                    <div class="col-12 col-xl-8 d-flex flex-column gap-3">
                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Tenant profile</p>
                                    <h3 class="dashboard-panel-title">Current contact and timeline</h3>
                                </div>
                            </div>

                            <div class="two-up-grid">
                                <div class="field-group">
                                    <span class="field-label">Email</span>
                                    <div class="field-input">{{ $tenant->email ?: 'Not provided' }}</div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Phone</span>
                                    <div class="field-input">{{ $tenant->phone ?: 'Not provided' }}</div>
                                </div>
                            </div>

                            <div class="two-up-grid">
                                <div class="field-group">
                                    <span class="field-label">Move-out date</span>
                                    <div class="field-input">{{ $tenant->move_out_on?->format('M j, Y') ?: 'Not scheduled' }}</div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Linked unit</span>
                                    <div class="field-input">{{ $tenant->unit->property->title }} · {{ $tenant->unit->unit_number }}</div>
                                </div>
                            </div>

                            <div class="field-group">
                                <span class="field-label">Notes</span>
                                <div class="field-input">{{ $tenant->notes ?: 'No notes recorded for this tenant yet.' }}</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">KYC documents</p>
                                    <h3 class="dashboard-panel-title">Uploaded files</h3>
                                </div>
                            </div>

                            @forelse ($tenant->documents as $document)
                                <article class="security-log-item">
                                    <div class="security-log-head">
                                        <span class="badge badge-outline">{{ str($document->document_type)->replace('_', ' ')->title() }}</span>
                                        <span class="security-log-meta">{{ $document->uploaded_at?->format('M j, Y') }}</span>
                                    </div>
                                    <p class="security-log-meta"><a href="{{ $document->url() }}" target="_blank" rel="noreferrer">{{ $document->original_name }}</a></p>
                                </article>
                            @empty
                                <p class="security-empty">No KYC documents have been uploaded for this tenant yet.</p>
                            @endforelse
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection