@extends('layouts.app', ['title' => 'Finance | PropMgr'])

@section('content')
    @php
        $upcomingExpected = $upcomingDues->sum(fn ($ledger) => (float) $ledger->outstanding_balance);
        $overdueExpected = $overdue->sum(fn ($ledger) => (float) $ledger->outstanding_balance);
        $arrearsExpected = $arrearsTracker->sum(fn ($ledger) => (float) $ledger->carried_arrears);
        $recentlyRecordedTotal = $recentlyRecorded->sum(fn ($instalment) => (float) $instalment->amount_paid);
    @endphp

    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 4 dashboard</p>
                        <h1 class="page-title">Rent operations dashboard</h1>
                        <p class="page-description">Review upcoming dues, partial collections, overdue ledgers, arrears carry-forward, and recent payment activity across the visible lease portfolio.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('dashboard') }}">Dashboard</a>
                    </div>
                </section>

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Upcoming dues</p>
                        <h2 class="stat-value">{{ $upcomingDues->count() }}</h2>
                        <p class="stat-meta"><span>{{ number_format($upcomingExpected, 2) }} expected</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Partially paid</p>
                        <h2 class="stat-value">{{ $partiallyPaid->count() }}</h2>
                        <p class="stat-meta"><span>open with receipts logged</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Overdue</p>
                        <h2 class="stat-value">{{ $overdue->count() }}</h2>
                        <p class="stat-meta"><span>{{ number_format($overdueExpected, 2) }} outstanding</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Arrears tracker</p>
                        <h2 class="stat-value">{{ $arrearsTracker->count() }}</h2>
                        <p class="stat-meta"><span>{{ number_format($arrearsExpected, 2) }} brought forward</span></p>
                    </article>
                </section>

                <article class="form-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Finance filters</p>
                            <h3 class="dashboard-panel-title">Narrow rent activity</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('finance.index') }}">
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Property</span>
                                <select class="field-input" name="property_id">
                                    <option value="">All properties</option>
                                    @foreach ($propertyOptions as $propertyOption)
                                        <option value="{{ $propertyOption->id }}" @selected((int) ($filters['property_id'] ?? 0) === $propertyOption->id)>{{ $propertyOption->title }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="field-group">
                                <span class="field-label">Unit</span>
                                <select class="field-input" name="unit_id">
                                    <option value="">All units</option>
                                    @foreach ($unitOptions as $unitOption)
                                        <option value="{{ $unitOption->id }}" @selected((int) ($filters['unit_id'] ?? 0) === $unitOption->id)>{{ $unitOption->property->title }} · {{ $unitOption->unit_number }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-solid" type="submit">Apply filters</button>
                            <a class="btn btn-ghost" href="{{ route('finance.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Upcoming dues</p>
                                    <h3 class="dashboard-panel-title">Next 30 days</h3>
                                </div>
                                <span class="badge badge-outline">{{ number_format($upcomingExpected, 2) }}</span>
                            </div>

                            @if ($upcomingDues->isEmpty())
                                <p class="security-empty">No upcoming dues matched the current scope.</p>
                            @else
                                <div class="table-head">
                                    <span>Lease</span>
                                    <span>Due</span>
                                    <span>Outstanding</span>
                                    <span>Action</span>
                                </div>
                                @foreach ($upcomingDues as $ledger)
                                    <div class="table-row">
                                        <div>
                                            <div class="tenant-name">{{ $ledger->lease->tenant->full_name }}</div>
                                            <div class="tenant-unit">{{ $ledger->lease->unit->property->title }} · {{ $ledger->lease->unit->unit_number }} · {{ $ledger->payment_month->format('F Y') }}</div>
                                        </div>
                                        <div class="muted-text">{{ $ledger->due_on->format('M j, Y') }}</div>
                                        <div class="muted-text">{{ number_format((float) $ledger->outstanding_balance, 2) }}</div>
                                        <div><a class="btn btn-ghost btn-sm" href="{{ route('leases.payments.show', $ledger->lease) }}">Open</a></div>
                                    </div>
                                @endforeach
                            @endif
                        </article>

                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Partially paid</p>
                                    <h3 class="dashboard-panel-title">Open months with receipts</h3>
                                </div>
                            </div>

                            @if ($partiallyPaid->isEmpty())
                                <p class="security-empty">No partially paid ledgers matched the current scope.</p>
                            @else
                                <div class="table-head">
                                    <span>Lease</span>
                                    <span>Received</span>
                                    <span>Outstanding</span>
                                    <span>Action</span>
                                </div>
                                @foreach ($partiallyPaid as $ledger)
                                    <div class="table-row">
                                        <div>
                                            <div class="tenant-name">{{ $ledger->lease->tenant->full_name }}</div>
                                            <div class="tenant-unit">{{ $ledger->lease->unit->property->title }} · {{ $ledger->lease->unit->unit_number }} · {{ $ledger->payment_month->format('F Y') }}</div>
                                        </div>
                                        <div class="muted-text">{{ number_format((float) $ledger->total_received, 2) }}</div>
                                        <div class="muted-text">{{ number_format((float) $ledger->outstanding_balance, 2) }}</div>
                                        <div><a class="btn btn-ghost btn-sm" href="{{ route('leases.payments.show', $ledger->lease) }}">Open</a></div>
                                    </div>
                                @endforeach
                            @endif
                        </article>

                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Overdue</p>
                                    <h3 class="dashboard-panel-title">Past grace period</h3>
                                </div>
                                <span class="badge badge-outline">{{ number_format($overdueExpected, 2) }}</span>
                            </div>

                            @if ($overdue->isEmpty())
                                <p class="security-empty">No overdue ledgers matched the current scope.</p>
                            @else
                                <div class="table-head">
                                    <span>Lease</span>
                                    <span>Due</span>
                                    <span>Outstanding</span>
                                    <span>Action</span>
                                </div>
                                @foreach ($overdue as $ledger)
                                    <div class="table-row">
                                        <div>
                                            <div class="tenant-name">{{ $ledger->lease->tenant->full_name }}</div>
                                            <div class="tenant-unit">{{ $ledger->lease->unit->property->title }} · {{ $ledger->lease->unit->unit_number }} · {{ $ledger->payment_month->format('F Y') }}</div>
                                        </div>
                                        <div class="muted-text">{{ $ledger->due_on->format('M j, Y') }}</div>
                                        <div class="muted-text">{{ number_format((float) $ledger->outstanding_balance, 2) }}</div>
                                        <div><a class="btn btn-ghost btn-sm" href="{{ route('leases.payments.show', $ledger->lease) }}">Open</a></div>
                                    </div>
                                @endforeach
                            @endif
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Arrears tracker</p>
                                    <h3 class="dashboard-panel-title">Carry-forward exposure</h3>
                                </div>
                                <span class="badge badge-outline">{{ number_format($arrearsExpected, 2) }}</span>
                            </div>

                            @if ($arrearsTracker->isEmpty())
                                <p class="security-empty">No arrears carry-forward matched the current scope.</p>
                            @else
                                @foreach ($arrearsTracker as $ledger)
                                    <div class="table-row">
                                        <div>
                                            <div class="tenant-name">{{ $ledger->lease->tenant->full_name }}</div>
                                            <div class="tenant-unit">{{ $ledger->payment_month->format('F Y') }} · {{ $ledger->lease->unit->unit_number }}</div>
                                        </div>
                                        <div class="muted-text">{{ number_format((float) $ledger->carried_arrears, 2) }}</div>
                                    </div>
                                @endforeach
                            @endif
                        </article>

                        <article class="feed-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Recently recorded</p>
                                    <h3 class="dashboard-panel-title">Last 7 days</h3>
                                </div>
                                <span class="badge badge-outline">{{ number_format($recentlyRecordedTotal, 2) }}</span>
                            </div>

                            @forelse ($recentlyRecorded as $instalment)
                                <div class="feed-item">
                                    <div class="feed-rail">
                                        <span class="feed-dot is-green"></span>
                                        @if (! $loop->last)
                                            <span class="feed-line"></span>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="feed-text">{{ $instalment->ledger->lease->tenant->full_name }} · {{ number_format((float) $instalment->amount_paid, 2) }}</p>
                                        <p class="feed-meta">{{ str($instalment->payment_mode)->replace('_', ' ')->title() }} · {{ $instalment->ledger->lease->unit->property->title }} · {{ $instalment->payment_date->format('M j, Y') }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="security-empty">No instalments were recorded in the last 7 days.</p>
                            @endforelse
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection