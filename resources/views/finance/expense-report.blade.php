@extends('layouts.app', ['title' => 'Expense Report | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 7 reporting</p>
                        <h1 class="page-title">Expenses report</h1>
                        <p class="page-description">Track expense entries by property, category, status, and review posture.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('finance.index') }}">Back to finance</a>
                    </div>
                </section>

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Expense rows</p>
                        <h2 class="stat-value">{{ $summary['count'] }}</h2>
                        <p class="stat-meta"><span>filtered entries</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Total expense</p>
                        <h2 class="stat-value">{{ number_format($summary['amount_total'], 2) }}</h2>
                        <p class="stat-meta"><span>all visible rows</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Approved total</p>
                        <h2 class="stat-value">{{ number_format($summary['approved_total'], 2) }}</h2>
                        <p class="stat-meta"><span>approved status</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Pending review</p>
                        <h2 class="stat-value">{{ number_format($summary['pending_review_total'], 2) }}</h2>
                        <p class="stat-meta"><span>awaiting decision</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">High value rows</p>
                        <h2 class="stat-value">{{ $summary['high_value_count'] }}</h2>
                        <p class="stat-meta"><span>amount >= 50,000</span></p>
                    </article>
                </section>

                <article class="form-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Filters</p>
                            <h3 class="dashboard-panel-title">Property, status, category, and date range</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('finance.reports.expenses.index') }}">
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
                                        @continue($status === 'approved' || $status === 'pending_review' || $status === 'rejected' ? false : true)
                                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Category</span>
                                <select class="field-input" name="category">
                                    <option value="all" @selected($filters['category'] === 'all')>All categories</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ str($category)->replace('_', ' ')->title() }}</option>
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
                            <button class="btn btn-solid" type="submit">Apply filters</button>
                            <a class="btn btn-ghost" href="{{ route('finance.reports.expenses.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                @php
                    $exportQuery = [
                        'property_id' => $filters['property_id'],
                        'status' => $filters['status'],
                        'category' => $filters['category'],
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
                        <a class="btn btn-solid btn-sm" href="{{ route('finance.reports.expenses.csv', $exportQuery) }}">Export CSV</a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('finance.reports.expenses.pdf', $exportQuery) }}">Export PDF</a>
                    </div>
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Expense rows</p>
                            <h3 class="dashboard-panel-title">Ledger expenses</h3>
                        </div>
                    </div>

                    @if ($rows->isEmpty())
                        <p class="security-empty">No expense entries matched the selected filters.</p>
                    @else
                        <div class="data-table-card">
                            <table class="data-table data-table-compact">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Vendor</th>
                                        <th>Reference</th>
                                        <th>Flagged reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row->property?->title }}</td>
                                            <td>{{ $row->entry_date?->format('M j, Y') }}</td>
                                            <td>{{ str($row->category)->replace('_', ' ')->title() }}</td>
                                            <td>{{ number_format((float) $row->amount, 2) }}</td>
                                            <td>{{ str($row->status)->replace('_', ' ')->title() }}</td>
                                            <td>{{ $row->vendor_name ?: 'N/A' }}</td>
                                            <td>{{ $row->reference_number ?: 'N/A' }}</td>
                                            <td>{{ $row->flagged_reason ?: 'None' }}</td>
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
