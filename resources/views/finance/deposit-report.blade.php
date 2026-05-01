@extends('layouts.app', ['title' => 'Deposits Report | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 7 reporting</p>
                        <h1 class="page-title">Deposits report</h1>
                        <p class="page-description">Track deposit account balances, adjustments, and settlement posture across visible properties.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('finance.index') }}">Back to finance</a>
                    </div>
                </section>

                <section class="row g-3">
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Deposit accounts</p>
                        <h2 class="stat-value">{{ $summary['count'] }}</h2>
                        <p class="stat-meta"><span>filtered rows</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Expected total</p>
                        <h2 class="stat-value">{{ number_format($summary['expected_total'], 2) }}</h2>
                        <p class="stat-meta"><span>target deposits</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Held balance</p>
                        <h2 class="stat-value">{{ number_format($summary['balance_total'], 2) }}</h2>
                        <p class="stat-meta"><span>current balances</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Collected total</p>
                        <h2 class="stat-value">{{ number_format($summary['collected_total'], 2) }}</h2>
                        <p class="stat-meta"><span>including initial collection</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Released total</p>
                        <h2 class="stat-value">{{ number_format($summary['released_total'], 2) }}</h2>
                        <p class="stat-meta"><span>refund + deduction + forfeiture</span></p>
                    </article>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Filters</p>
                            <h3 class="dashboard-panel-title">Property, status, entry type, and date range</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('finance.reports.deposits.index') }}">
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
                                <span class="field-label">Status</span>
                                <select class="field-input" name="status">
                                    <option value="all" @selected($filters['status'] === 'all')>All statuses</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Entry type</span>
                                <select class="field-input" name="entry_type">
                                    <option value="all" @selected($filters['entry_type'] === 'all')>All entry types</option>
                                    @foreach ($entryTypes as $entryType)
                                        <option value="{{ $entryType }}" @selected($filters['entry_type'] === $entryType)>{{ str($entryType)->replace('_', ' ')->title() }}</option>
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
                            <a class="btn btn-outline-secondary" href="{{ route('finance.reports.deposits.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                @php
                    $exportQuery = [
                        'property_id' => $filters['property_id'],
                        'status' => $filters['status'],
                        'entry_type' => $filters['entry_type'],
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
                        <a class="btn btn-primary btn-sm" href="{{ route('finance.reports.deposits.csv', $exportQuery) }}">Export CSV</a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.reports.deposits.pdf', $exportQuery) }}">Export PDF</a>
                    </div>
                </article>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Deposit accounts</p>
                            <h3 class="dashboard-panel-title">Balances and adjustment totals</h3>
                        </div>
                    </div>

                    @if ($rows->isEmpty())
                        <p class="security-empty">No deposit accounts matched the selected filters.</p>
                    @else
                        <div class="">
                            <table class="data-table data-table-compact table w-100">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Unit</th>
                                        <th>Lease</th>
                                        <th>Tenant</th>
                                        <th>Status</th>
                                        <th>Expected</th>
                                        <th>Balance</th>
                                        <th>Collected</th>
                                        <th>Released</th>
                                        <th>Entries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row->lease?->unit?->property?->title }}</td>
                                            <td>{{ $row->lease?->unit?->unit_number }}</td>
                                            <td>{{ $row->lease?->lease_number }}</td>
                                            <td>{{ $row->lease?->tenant?->full_name }}</td>
                                            <td>{{ str($row->status)->replace('_', ' ')->title() }}</td>
                                            <td>{{ number_format((float) $row->expected_amount, 2) }}</td>
                                            <td>{{ number_format((float) $row->current_balance, 2) }}</td>
                                            <td>{{ number_format((float) $row->collected_total + (float) $row->top_up_total, 2) }}</td>
                                            <td>{{ number_format((float) $row->deducted_total + (float) $row->refunded_total + (float) $row->forfeited_total, 2) }}</td>
                                            <td>{{ (int) ($row->entries_count ?? 0) }}</td>
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
