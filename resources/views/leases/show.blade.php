@extends('layouts.app', ['title' => 'Lease Detail | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Lease detail</p>
                        <h1 class="page-title">{{ $lease->lease_number }}</h1>
                        <p class="page-description">Lease lifecycle and renewal state for {{ $lease->tenant->full_name }} at {{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ $user?->hasRole('tenant') ? route('dashboard') : route('leases.index') }}">{{ $user?->hasRole('tenant') ? 'Back to portal' : 'Back to leases' }}</a>
                        <a class="btn btn-ghost" href="{{ route('leases.payments.show', $lease) }}">Payment history</a>
                        @if ($lease->rentReturn)
                            <a class="btn btn-ghost" href="{{ route('leases.rent-return.show', [$lease, $lease->rentReturn]) }}">Rent return</a>
                        @elseif ($rentReturnDraft && (float) $rentReturnDraft['suggested_amount'] > 0)
                            @can('update', $lease)
                                <a class="btn btn-ghost" href="{{ route('leases.rent-return.create', $lease) }}">Process Rent Return</a>
                            @endcan
                        @endif
                        @can('update', $lease)
                            <a class="btn btn-solid" href="{{ route('leases.edit', $lease) }}">Edit lease</a>
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
                        <p class="stat-label">Tenant</p>
                        <h2 class="stat-value">{{ $lease->tenant->full_name }}</h2>
                        <p class="stat-meta"><span>{{ $lease->unit->unit_number }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Status</p>
                        <h2 class="stat-value">{{ str($lease->status)->replace('_', ' ')->title() }}</h2>
                        <p class="stat-meta"><span>{{ $lease->isActive() ? 'occupancy enforced' : 'not current active lease' }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Rent</p>
                        <h2 class="stat-value">{{ number_format((float) $lease->rent_amount, 2) }}</h2>
                        <p class="stat-meta"><span>billing day {{ $lease->billing_day }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Dates</p>
                        <h2 class="stat-value">{{ $lease->start_on->format('M j') }}</h2>
                        <p class="stat-meta"><span>to {{ $lease->end_on->format('M j, Y') }}</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="form-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Lease profile</p>
                                    <h3 class="dashboard-panel-title">Current commercial record</h3>
                                </div>
                            </div>

                            <div class="two-up-grid">
                                <div class="field-group">
                                    <span class="field-label">Unit</span>
                                    <div class="field-input">{{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}</div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Previous lease</span>
                                    <div class="field-input">{{ $lease->previousLease?->lease_number ?: 'None' }}</div>
                                </div>
                            </div>

                            <div class="field-group">
                                <span class="field-label">Notes</span>
                                <div class="field-input">{{ $lease->notes ?: 'No notes recorded for this lease yet.' }}</div>
                            </div>

                            @if ($lease->rentReturn)
                                <div class="field-group">
                                    <span class="field-label">Rent return</span>
                                    <div class="field-input">
                                        <strong>{{ str($lease->rentReturn->status)->replace('_', ' ')->title() }}</strong>
                                        · suggested {{ number_format((float) $lease->rentReturn->suggested_amount, 2) }}
                                        @if ($lease->rentReturn->confirmed_amount !== null)
                                            · confirmed {{ number_format((float) $lease->rentReturn->confirmed_amount, 2) }}
                                        @endif
                                    </div>
                                </div>
                            @elseif ($rentReturnDraft && (float) $rentReturnDraft['suggested_amount'] > 0)
                                <div class="field-group">
                                    <span class="field-label">Rent return</span>
                                    <div class="field-input">
                                        Potential overpayment detected from {{ $rentReturnDraft['vacation_date']->toDateString() }} to {{ $rentReturnDraft['last_paid_through_date']?->toDateString() }}.
                                        @can('update', $lease)
                                            <a href="{{ route('leases.rent-return.create', $lease) }}">Process Rent Return</a>
                                        @endcan
                                    </div>
                                </div>
                            @endif

                            <div class="two-up-grid">
                                <div class="field-group">
                                    <span class="field-label">Grace period</span>
                                    <div class="field-input">{{ $lease->grace_period_days }} days</div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Late fee rule</span>
                                    <div class="field-input">{{ $lease->late_fee_mode === 'percentage' ? number_format((float) $lease->late_fee_value, 2).'%' : number_format((float) $lease->late_fee_value, 2) }}</div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Renewal</p>
                                    <h3 class="dashboard-panel-title">Create successor lease</h3>
                                </div>
                            </div>

                            @can('renew', $lease)
                                <form method="POST" action="{{ route('leases.renew', $lease) }}">
                                    @csrf
                                    <label class="field-group">
                                        <span class="field-label">Next start date</span>
                                        <input class="field-input" type="date" name="start_on" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Next end date</span>
                                        <input class="field-input" type="date" name="end_on" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Next rent amount</span>
                                        <input class="field-input" type="number" min="0" step="0.01" name="rent_amount" value="{{ $lease->rent_amount }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Billing day</span>
                                        <input class="field-input" type="number" min="1" max="28" name="billing_day" value="{{ $lease->billing_day }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Renewal notes</span>
                                        <textarea class="field-input" name="notes" rows="3"></textarea>
                                    </label>
                                    <button class="btn btn-solid" type="submit">Renew lease</button>
                                </form>
                            @else
                                <p class="security-empty">This lease is visible in read-only mode from the tenant portal.</p>
                            @endcan
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection