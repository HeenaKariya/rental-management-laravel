@extends('layouts.app', ['title' => 'Rent Return Report | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 7 reporting</p>
                        <h1 class="page-title">Rent return report</h1>
                        <p class="page-description">Track settlement state, ledger posting, and payout signals across all visible leases.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('finance.index') }}">Back to finance</a>
                    </div>
                </section>

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Total records</p>
                        <h2 class="stat-value">{{ $summary['count'] }}</h2>
                        <p class="stat-meta"><span>matches current filter</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Suggested total</p>
                        <h2 class="stat-value">{{ number_format($summary['suggested_total'], 2) }}</h2>
                        <p class="stat-meta"><span>raw calculated refund</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Confirmed total</p>
                        <h2 class="stat-value">{{ number_format($summary['confirmed_total'], 2) }}</h2>
                        <p class="stat-meta"><span>after overrides</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Settled amount</p>
                        <h2 class="stat-value">{{ number_format($summary['settled_total'], 2) }}</h2>
                        <p class="stat-meta"><span>actual settlement amount</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Ledger posted</p>
                        <h2 class="stat-value">{{ $summary['posted_count'] }}</h2>
                        <p class="stat-meta"><span>entries marked posted</span></p>
                    </article>
                </section>

                <article class="form-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Filters</p>
                            <h3 class="dashboard-panel-title">Property, status, date range</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('finance.reports.rent-returns.index') }}">
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
                                <span class="field-label">From</span>
                                <input class="field-input" type="date" name="date_from" value="{{ $filters['date_from']?->toDateString() }}">
                            </label>

                            <label class="field-group">
                                <span class="field-label">To</span>
                                <input class="field-input" type="date" name="date_to" value="{{ $filters['date_to']?->toDateString() }}">
                            </label>
                        </div>

                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-solid" type="submit">Apply filters</button>
                            <a class="btn btn-ghost" href="{{ route('finance.reports.rent-returns.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                @php
                    $exportQuery = [
                        'property_id' => $filters['property_id'],
                        'status' => $filters['status'],
                        'date_from' => $filters['date_from']?->toDateString(),
                        'date_to' => $filters['date_to']?->toDateString(),
                    ];
                @endphp

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Exports</p>
                            <h3 class="dashboard-panel-title">CSV and PDF downloads</h3>
                        </div>
                    </div>
                    <p class="security-meta" style="margin-bottom: 0.75rem;">Current period: {{ $filters['range_label'] }}</p>
                    <div class="btn-strip">
                        <a class="btn btn-solid btn-sm" href="{{ route('finance.reports.rent-returns.csv', $exportQuery) }}">Export CSV</a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('finance.reports.rent-returns.pdf', $exportQuery) }}">Export PDF</a>
                    </div>
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Rent return records</p>
                            <h3 class="dashboard-panel-title">Settlement and posting visibility</h3>
                        </div>
                    </div>

                    @if ($rows->isEmpty())
                        <p class="security-empty">No rent return records match the selected filters.</p>
                    @else
                        <div class="data-table-card">
                            <table class="data-table data-table-compact">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Lease</th>
                                        <th>Tenant</th>
                                        <th>Status</th>
                                        <th>Suggested</th>
                                        <th>Confirmed</th>
                                        <th>Settlement method</th>
                                        <th>Settlement amount</th>
                                        <th>Ledger posted</th>
                                        <th>Initiated</th>
                                        <th>Processed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row->property?->title }}</td>
                                            <td>
                                                @if ($row->lease)
                                                    <a href="{{ route('leases.rent-return.show', [$row->lease, $row]) }}">{{ $row->lease->lease_number }}</a>
                                                @endif
                                            </td>
                                            <td>{{ $row->tenant?->full_name }}</td>
                                            <td>{{ str($row->status)->replace('_', ' ')->title() }}</td>
                                            <td>{{ number_format((float) $row->suggested_amount, 2) }}</td>
                                            <td>{{ number_format((float) ($row->confirmed_amount ?? 0), 2) }}</td>
                                            <td>{{ str((string) ($row->settlement_method ?? 'n/a'))->replace('_', ' ')->title() }}</td>
                                            <td>{{ number_format((float) ($row->settlement_amount ?? 0), 2) }}</td>
                                            <td>{{ $row->ledger_posted ? 'Yes' : 'No' }}</td>
                                            <td>{{ $row->initiated_at?->format('M j, Y') }}</td>
                                            <td>{{ $row->processed_at?->format('M j, Y') ?? 'Pending' }}</td>
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
