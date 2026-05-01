@extends('layouts.app', ['title' => 'Rent Collection Report | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 7 reporting</p>
                        <h1 class="page-title">Rent collection report</h1>
                        <p class="page-description">Analyze instalment-level rent collections by date, property scope, and payment mode.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('finance.index') }}">Back to finance</a>
                    </div>
                </section>

                <section class="row g-3">
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Instalments</p>
                        <h2 class="stat-value">{{ $summary['count'] }}</h2>
                        <p class="stat-meta"><span>matched receipts</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Amount paid</p>
                        <h2 class="stat-value">{{ number_format($summary['amount_paid_total'], 2) }}</h2>
                        <p class="stat-meta"><span>total collections</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Late fee total</p>
                        <h2 class="stat-value">{{ number_format($summary['late_fee_total'], 2) }}</h2>
                        <p class="stat-meta"><span>charged on receipts</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Cash total</p>
                        <h2 class="stat-value">{{ number_format($summary['cash_total'], 2) }}</h2>
                        <p class="stat-meta"><span>cash mode</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Digital total</p>
                        <h2 class="stat-value">{{ number_format($summary['digital_total'], 2) }}</h2>
                        <p class="stat-meta"><span>bank transfer + UPI</span></p>
                    </article>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Filters</p>
                            <h3 class="dashboard-panel-title">Property, payment mode, and date range</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('finance.reports.rent-collection.index') }}">
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
                                <span class="field-label">Payment mode</span>
                                <select class="field-input" name="payment_mode">
                                    <option value="all" @selected($filters['payment_mode'] === 'all')>All modes</option>
                                    @foreach ($paymentModes as $mode)
                                        <option value="{{ $mode }}" @selected($filters['payment_mode'] === $mode)>{{ str($mode)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Date from</span>
                                <input class="field-input" type="date" name="date_from" value="{{ $filters['date_from']?->toDateString() }}">
                            </label>
                            <label class="field-group">
                                <span class="field-label">Date to</span>
                                <input class="field-input" type="date" name="date_to" value="{{ $filters['date_to']?->toDateString() }}">
                            </label>
                        </div>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-primary" type="submit">Apply filters</button>
                            <a class="btn btn-outline-secondary" href="{{ route('finance.reports.rent-collection.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                @php
                    $exportQuery = [
                        'property_id' => $filters['property_id'],
                        'payment_mode' => $filters['payment_mode'],
                        'date_from' => $filters['date_from']?->toDateString(),
                        'date_to' => $filters['date_to']?->toDateString(),
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
                        <a class="btn btn-primary btn-sm" href="{{ route('finance.reports.rent-collection.csv', $exportQuery) }}">Export CSV</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.reports.rent-collection.pdf', $exportQuery) }}">Export PDF</a>
                    </div>
                </article>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Collection rows</p>
                            <h3 class="dashboard-panel-title">Rent instalments</h3>
                        </div>
                    </div>

                    @if ($rows->isEmpty())
                        <p class="security-empty">No rent collections matched the selected filters.</p>
                    @else
                        <div class="">
                            <table class="data-table data-table-compact table w-100">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th>Lease</th>
                                        <th>Tenant</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Late fee</th>
                                        <th>Mode</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row->ledger?->lease?->unit?->property?->title }}</td>
                                            <td>{{ $row->ledger?->lease?->unit?->unit_number }}</td>
                                            <td>{{ $row->ledger?->lease?->lease_number }}</td>
                                            <td>{{ $row->ledger?->lease?->tenant?->full_name }}</td>
                                            <td>{{ $row->payment_date?->format('M j, Y') }}</td>
                                            <td>{{ number_format((float) $row->amount_paid, 2) }}</td>
                                            <td>{{ number_format((float) $row->late_fee_charged, 2) }}</td>
                                            <td>{{ str($row->payment_mode)->replace('_', ' ')->title() }}</td>
                                            <td>{{ $row->reference_number ?: 'N/A' }}</td>
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
