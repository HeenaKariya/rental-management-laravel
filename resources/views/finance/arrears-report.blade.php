@extends('layouts.app', ['title' => 'Arrears Report | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 7 reporting</p>
                        <h1 class="page-title">Arrears and partial payments report</h1>
                        <p class="page-description">Review outstanding rent ledgers month-wise with instalment activity and arrears carry-forward risk signals.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('finance.index') }}">Back to finance</a>
                    </div>
                </section>

                <section class="row g-3">
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Ledger rows</p>
                        <h2 class="stat-value">{{ $summary['count'] }}</h2>
                        <p class="stat-meta"><span>filtered rows</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Outstanding total</p>
                        <h2 class="stat-value">{{ number_format($summary['outstanding_total'], 2) }}</h2>
                        <p class="stat-meta"><span>open balances</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Carried arrears</p>
                        <h2 class="stat-value">{{ number_format($summary['carried_arrears_total'], 2) }}</h2>
                        <p class="stat-meta"><span>brought forward</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Partial + overdue</p>
                        <h2 class="stat-value">{{ $summary['partial_count'] + $summary['overdue_count'] }}</h2>
                        <p class="stat-meta"><span>{{ $summary['partial_count'] }} partial / {{ $summary['overdue_count'] }} overdue</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Alerted leases</p>
                        <h2 class="stat-value">{{ $summary['alerted_count'] }}</h2>
                        <p class="stat-meta"><span>threshold breached</span></p>
                    </article>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Filters</p>
                            <h3 class="dashboard-panel-title">Property, unit, status, and date range</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('finance.reports.arrears.index') }}">
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Property</span>
                                <select class="field-input" name="property_id">
                                    <option value="">All properties</option>
                                    @foreach ($properties as $property)
                                        <option value="{{ $property->id }}" @selected($filters['property_id'] === $property->id)>{{ $property->title }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Unit</span>
                                <select class="field-input" name="unit_id">
                                    <option value="">All units</option>
                                    @foreach ($unitOptions as $unit)
                                        <option value="{{ $unit->id }}" @selected($filters['unit_id'] === $unit->id)>{{ $unit->unit_number }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Status</span>
                                <select class="field-input" name="status">
                                    <option value="all" @selected($filters['status'] === 'all')>All statuses</option>
                                    <option value="unpaid" @selected($filters['status'] === 'unpaid')>Unpaid</option>
                                    <option value="partially_paid" @selected($filters['status'] === 'partially_paid')>Partially paid</option>
                                    <option value="overdue" @selected($filters['status'] === 'overdue')>Overdue</option>
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">From month</span>
                                <input class="field-input" type="date" name="date_from" value="{{ $filters['date_from']?->toDateString() }}">
                            </label>
                            <label class="field-group">
                                <span class="field-label">To month</span>
                                <input class="field-input" type="date" name="date_to" value="{{ $filters['date_to']?->toDateString() }}">
                            </label>
                            <label class="field-group">
                                <span class="field-label">Alert threshold months</span>
                                <input class="field-input" type="number" min="1" max="24" name="alert_threshold_months" value="{{ $filters['alert_threshold_months'] }}">
                            </label>
                        </div>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-primary" type="submit">Apply filters</button>
                            <a class="btn btn-outline-secondary" href="{{ route('finance.reports.arrears.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                @php
                    $exportQuery = [
                        'property_id' => $filters['property_id'],
                        'unit_id' => $filters['unit_id'],
                        'status' => $filters['status'],
                        'date_from' => $filters['date_from']?->toDateString(),
                        'date_to' => $filters['date_to']?->toDateString(),
                        'alert_threshold_months' => $filters['alert_threshold_months'],
                    ];
                @endphp

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Exports</p>
                            <h3 class="dashboard-panel-title">CSV and PDF downloads</h3>
                        </div>
                    </div>

                    <p class="security-meta" style="margin-bottom: 0.75rem;">Current period: {{ $filters['range_label'] }}</p>

                    <div class="btn-strip">
                        <a class="btn btn-primary btn-sm" href="{{ route('finance.reports.arrears.csv', $exportQuery) }}">Export CSV</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.reports.arrears.pdf', $exportQuery) }}">Export PDF</a>
                    </div>
                </article>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Month-wise ledger rows</p>
                            <h3 class="dashboard-panel-title">Arrears history and instalment breakdown</h3>
                        </div>
                    </div>

                    @if ($rows->isEmpty())
                        <p class="security-empty">No arrears rows matched the selected filters.</p>
                    @else
                        <div class="">
                            <table class="data-table data-table-compact table w-100">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th>Lease</th>
                                        <th>Tenant</th>
                                        <th>Month</th>
                                        <th>Status</th>
                                        <th>Carried arrears</th>
                                        <th>Outstanding</th>
                                        <th>Instalments</th>
                                        <th>Instalments paid</th>
                                        <th>Last payment</th>
                                        <th>Alert</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row->lease?->unit?->property?->title }}</td>
                                            <td>{{ $row->lease?->unit?->unit_number }}</td>
                                            <td>{{ $row->lease?->lease_number }}</td>
                                            <td>{{ $row->lease?->tenant?->full_name }}</td>
                                            <td>{{ $row->payment_month?->format('M Y') }}</td>
                                            <td>{{ str($row->status)->replace('_', ' ')->title() }}</td>
                                            <td>{{ number_format((float) $row->carried_arrears, 2) }}</td>
                                            <td>{{ number_format((float) $row->outstanding_balance, 2) }}</td>
                                            <td>{{ (int) ($row->instalments_count ?? 0) }}</td>
                                            <td>{{ number_format((float) ($row->instalments_paid_total ?? 0), 2) }}</td>
                                            <td>{{ $row->last_payment_date ?: 'No payments' }}</td>
                                            <td>
                                                @if ($row->arrears_alert)
                                                    <span class="badge badge-warning">Alert ({{ $row->max_arrears_streak }} months)</span>
                                                @else
                                                    <span class="muted-text">No alert</span>
                                                @endif
                                            </td>
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
