@extends('layouts.app', ['title' => 'Finance Reports | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 5 finance</p>
                        <h1 class="page-title">Owner statement and report matrix · {{ $property->title }}</h1>
                        <p class="page-description">Downloadable owner statement and category-level P&L matrix in CSV and PDF.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('properties.show', $property) }}">Back to property</a>
                        <a class="btn btn-ghost" href="{{ route('properties.finance.ledger.index', $property) }}">Open ledger</a>
                    </div>
                </section>

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Operational income</p>
                        <h2 class="stat-value">{{ number_format((float) $operationalSummary['total_income'], 2) }}</h2>
                        <p class="stat-meta"><span>approved income entries</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Operational expense</p>
                        <h2 class="stat-value">{{ number_format((float) $operationalSummary['total_expense'], 2) }}</h2>
                        <p class="stat-meta"><span>approved expense entries</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Net operational</p>
                        <h2 class="stat-value">{{ number_format((float) $operationalSummary['net_operational_income'], 2) }}</h2>
                        <p class="stat-meta"><span>income minus expense</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Sale profit / loss</p>
                        <h2 class="stat-value">{{ number_format((float) $operationalSummary['sale_profit_loss'], 2) }}</h2>
                        <p class="stat-meta"><span>from closed sale snapshot</span></p>
                    </article>
                </section>

                <article class="form-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Date range</p>
                            <h3 class="dashboard-panel-title">Filter reporting period</h3>
                        </div>
                    </div>

                    <div class="btn-strip" style="margin-bottom: 0.75rem;">
                        <a class="btn btn-ghost btn-sm" href="{{ route('properties.finance.reports.show', [$property, 'period' => 'this_month']) }}">MTD</a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('properties.finance.reports.show', [$property, 'period' => 'this_quarter']) }}">QTD</a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('properties.finance.reports.show', [$property, 'period' => 'ytd']) }}">YTD</a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('properties.finance.reports.show', [$property, 'period' => 'all_time']) }}">All time</a>
                    </div>

                    <form method="GET" action="{{ route('properties.finance.reports.show', $property) }}">
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Period</span>
                                <select class="field-input" name="period">
                                    <option value="all_time" @selected($filters['period'] === 'all_time')>All time</option>
                                    <option value="this_month" @selected($filters['period'] === 'this_month')>This month</option>
                                    <option value="last_month" @selected($filters['period'] === 'last_month')>Last month</option>
                                    <option value="this_quarter" @selected($filters['period'] === 'this_quarter')>This quarter</option>
                                    <option value="last_quarter" @selected($filters['period'] === 'last_quarter')>Last quarter</option>
                                    <option value="ytd" @selected($filters['period'] === 'ytd')>Year to date</option>
                                    <option value="custom" @selected($filters['period'] === 'custom')>Custom range</option>
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">From</span>
                                <input class="field-input" type="date" name="date_from" value="{{ old('date_from', $filters['date_from']?->toDateString()) }}">
                            </label>
                            <label class="field-group">
                                <span class="field-label">To</span>
                                <input class="field-input" type="date" name="date_to" value="{{ old('date_to', $filters['date_to']?->toDateString()) }}">
                            </label>
                        </div>
                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-solid" type="submit">Apply filter</button>
                            <a class="btn btn-ghost" href="{{ route('properties.finance.reports.show', $property) }}">Reset</a>
                        </div>
                    </form>
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Exports</p>
                            <h3 class="dashboard-panel-title">CSV and PDF downloads</h3>
                        </div>
                    </div>

                    <p class="security-meta" style="margin-bottom: 0.75rem;">Current period: {{ $filters['range_label'] }}</p>

                    @php
                        $exportQuery = [
                            'period' => $filters['period'],
                            'date_from' => $filters['date_from']?->toDateString(),
                            'date_to' => $filters['date_to']?->toDateString(),
                        ];
                    @endphp

                    <div class="btn-strip">
                        <a class="btn btn-solid btn-sm" href="{{ route('properties.finance.reports.owner-statement.csv', [$property] + $exportQuery) }}">Owner statement CSV</a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('properties.finance.reports.owner-statement.pdf', [$property] + $exportQuery) }}">Owner statement PDF</a>
                        <a class="btn btn-solid btn-sm" href="{{ route('properties.finance.reports.pnl-matrix.csv', [$property] + $exportQuery) }}">P&amp;L matrix CSV</a>
                        <a class="btn btn-ghost btn-sm" href="{{ route('properties.finance.reports.pnl-matrix.pdf', [$property] + $exportQuery) }}">P&amp;L matrix PDF</a>
                    </div>
                </article>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Owner statement</p>
                                    <h3 class="dashboard-panel-title">Distribution view</h3>
                                </div>
                            </div>

                            @if (empty($ownerStatementRows))
                                <p class="security-empty">No active ownership rows found. Configure ownership splits to generate statement rows.</p>
                            @else
                                <div class="data-table-card">
                                    <table class="data-table data-table-compact">
                                        <thead>
                                            <tr>
                                                <th>Owner</th>
                                                <th>Ownership</th>
                                                <th>Income share</th>
                                                <th>Expense share</th>
                                                <th>Net ops share</th>
                                                <th>Sale share</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($ownerStatementRows as $row)
                                                <tr>
                                                    <td>{{ $row['owner_name'] }}</td>
                                                    <td>{{ number_format((float) $row['ownership_pct'], 2) }}%</td>
                                                    <td>{{ number_format((float) $row['income_share'], 2) }}</td>
                                                    <td>{{ number_format((float) $row['expense_share'], 2) }}</td>
                                                    <td>{{ number_format((float) $row['net_operational_share'], 2) }}</td>
                                                    <td>{{ number_format((float) $row['sale_share'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">P&amp;L matrix</p>
                                    <h3 class="dashboard-panel-title">Category net table</h3>
                                </div>
                            </div>

                            @if (empty($pnlMatrixRows))
                                <p class="security-empty">No approved ledger entries are available to build the matrix.</p>
                            @else
                                <div class="data-table-card">
                                    <table class="data-table data-table-compact">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Income</th>
                                                <th>Expense</th>
                                                <th>Net</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($pnlMatrixRows as $row)
                                                <tr>
                                                    <td>{{ $row['category'] }}</td>
                                                    <td>{{ number_format((float) $row['income_amount'], 2) }}</td>
                                                    <td>{{ number_format((float) $row['expense_amount'], 2) }}</td>
                                                    <td>{{ number_format((float) $row['net_amount'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection