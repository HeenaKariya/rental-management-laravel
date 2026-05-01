@extends('layouts.app', ['title' => 'Loan Schedule Report | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 7 reporting</p>
                        <h1 class="page-title">Loan schedule report</h1>
                        <p class="page-description">Track EMI payment progression and principal-interest split across visible properties.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('finance.index') }}">Back to finance</a>
                    </div>
                </section>

                <section class="row g-3">
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">EMI records</p>
                        <h2 class="stat-value">{{ $summary['count'] }}</h2>
                        <p class="stat-meta"><span>matched rows</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Amount paid</p>
                        <h2 class="stat-value">{{ number_format($summary['amount_paid_total'], 2) }}</h2>
                        <p class="stat-meta"><span>total paid</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Principal total</p>
                        <h2 class="stat-value">{{ number_format($summary['principal_total'], 2) }}</h2>
                        <p class="stat-meta"><span>principal reduction</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Interest total</p>
                        <h2 class="stat-value">{{ number_format($summary['interest_total'], 2) }}</h2>
                        <p class="stat-meta"><span>interest paid</span></p>
                    </article>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Filters</p>
                            <h3 class="dashboard-panel-title">Property and date range</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('finance.reports.loan-schedule.index') }}">
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
                                <span class="field-label">From</span>
                                <input class="field-input" type="date" name="date_from" value="{{ $filters['date_from']?->toDateString() }}">
                            </label>
                            <label class="field-group">
                                <span class="field-label">To</span>
                                <input class="field-input" type="date" name="date_to" value="{{ $filters['date_to']?->toDateString() }}">
                            </label>
                        </div>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-primary" type="submit">Apply filters</button>
                            <a class="btn btn-outline-secondary" href="{{ route('finance.reports.loan-schedule.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                @php
                    $exportQuery = [
                        'property_id' => $filters['property_id'],
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
                        <a class="btn btn-primary btn-sm" href="{{ route('finance.reports.loan-schedule.csv', $exportQuery) }}">Export CSV</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.reports.loan-schedule.pdf', $exportQuery) }}">Export PDF</a>
                    </div>
                </article>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Loan schedule rows</p>
                            <h3 class="dashboard-panel-title">EMI timeline</h3>
                        </div>
                    </div>

                    @if ($rows->isEmpty())
                        <p class="security-empty">No EMI records matched the selected filters.</p>
                    @else
                        <div class="">
                            <table class="data-table data-table-compact table w-100">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Lender</th>
                                        <th>EMI #</th>
                                        <th>Date paid</th>
                                        <th>Amount paid</th>
                                        <th>Principal</th>
                                        <th>Interest</th>
                                        <th>Outstanding</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row->loan?->property?->title }}</td>
                                            <td>{{ $row->loan?->lender_name }}</td>
                                            <td>{{ $row->emi_number }}</td>
                                            <td>{{ $row->date_paid?->format('M j, Y') }}</td>
                                            <td>{{ number_format((float) $row->amount_paid, 2) }}</td>
                                            <td>{{ number_format((float) $row->principal_component, 2) }}</td>
                                            <td>{{ number_format((float) $row->interest_component, 2) }}</td>
                                            <td>{{ number_format((float) $row->outstanding_balance, 2) }}</td>
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
