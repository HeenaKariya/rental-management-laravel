@extends('layouts.app', ['title' => 'Purchase and Loan | PropMgr'])

@section('content')
    @php
        $purchase = $property->purchase;
        $loan = $property->loan;
    @endphp

    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 5 finance</p>
                        <h1 class="page-title">Purchase and loan · {{ $property->title }}</h1>
                        <p class="page-description">Acquisition details, mortgage setup, and EMI payment log with summary metrics.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('properties.show', $property) }}">Back to property</a>
                        <a class="btn btn-ghost" href="{{ route('properties.finance.ledger.index', $property) }}">Open ledger</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Acquisition total</p>
                        <h2 class="stat-value">{{ number_format((float) ($purchase?->total_acquisition_cost ?? 0), 2) }}</h2>
                        <p class="stat-meta"><span>purchase plus duties and costs</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Loan amount</p>
                        <h2 class="stat-value">{{ number_format((float) ($loan?->loan_amount ?? 0), 2) }}</h2>
                        <p class="stat-meta"><span>{{ $loan?->lender_name ?: 'No loan configured' }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Total EMI paid</p>
                        <h2 class="stat-value">{{ number_format((float) ($loanSummary['total_emis_paid'] ?? 0), 2) }}</h2>
                        <p class="stat-meta"><span>interest paid {{ number_format((float) ($loanSummary['total_interest_paid'] ?? 0), 2) }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Outstanding principal</p>
                        <h2 class="stat-value">{{ number_format((float) ($loanSummary['outstanding_principal'] ?? 0), 2) }}</h2>
                        <p class="stat-meta"><span>remaining tenure {{ (int) ($loanSummary['remaining_tenure_months'] ?? 0) }} months</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Purchase record</p>
                                    <h3 class="dashboard-panel-title">Acquisition details</h3>
                                </div>
                            </div>

                            <div class="pending-row"><span>Purchase price</span><span>{{ number_format((float) ($purchase?->purchase_price ?? 0), 2) }}</span></div>
                            <div class="pending-row"><span>Purchase date</span><span>{{ $purchase?->purchase_date?->format('M j, Y') ?: 'Not recorded' }}</span></div>
                            <div class="pending-row"><span>Stamp duty</span><span>{{ number_format((float) ($purchase?->stamp_duty ?? 0), 2) }}</span></div>
                            <div class="pending-row"><span>Registration</span><span>{{ number_format((float) ($purchase?->registration_charges ?? 0), 2) }}</span></div>
                            <div class="pending-row"><span>Other costs</span><span>{{ number_format((float) ($purchase?->other_acquisition_costs ?? 0), 2) }}</span></div>
                            <div class="pending-row"><span>Seller</span><span>{{ $purchase?->seller_name ?: 'Not recorded' }}</span></div>
                            <div class="pending-row"><span>Seller contact</span><span>{{ $purchase?->seller_contact ?: 'Not recorded' }}</span></div>
                            <div class="pending-row"><span>Notes</span><span>{{ $purchase?->notes ?: 'No purchase notes recorded.' }}</span></div>
                        </article>

                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">EMI history</p>
                                    <h3 class="dashboard-panel-title">Loan payment log</h3>
                                </div>
                            </div>

                            @if (! $loan || $loan->emiLogs->isEmpty())
                                <p class="security-empty">No EMI records logged yet.</p>
                            @else
                                <div class="data-table-card">
                                    <table class="data-table data-table-compact">
                                        <thead>
                                            <tr>
                                                <th scope="col">EMI #</th>
                                                <th scope="col">Date paid</th>
                                                <th scope="col">Amount</th>
                                                <th scope="col">Principal</th>
                                                <th scope="col">Interest</th>
                                                <th scope="col">Outstanding</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($loan->emiLogs as $emiLog)
                                                <tr>
                                                    <td>{{ $emiLog->emi_number }}</td>
                                                    <td>{{ $emiLog->date_paid->format('M j, Y') }}</td>
                                                    <td>{{ number_format((float) $emiLog->amount_paid, 2) }}</td>
                                                    <td>{{ number_format((float) $emiLog->principal_component, 2) }}</td>
                                                    <td>{{ number_format((float) $emiLog->interest_component, 2) }}</td>
                                                    <td>{{ number_format((float) $emiLog->outstanding_balance, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        @can('update', $property)
                            <article class="security-card dashboard-panel">
                                <div class="dashboard-panel-head">
                                    <div>
                                        <p class="row-label">Purchase input</p>
                                        <h3 class="dashboard-panel-title">Save purchase details</h3>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('properties.finance.purchase.store', $property) }}">
                                    @csrf
                                    <label class="field-group">
                                        <span class="field-label">Purchase price</span>
                                        <input class="field-input" type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price', $purchase?->purchase_price) }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Purchase date</span>
                                        <input class="field-input" type="date" name="purchase_date" value="{{ old('purchase_date', $purchase?->purchase_date?->toDateString()) }}">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Stamp duty</span>
                                        <input class="field-input" type="number" step="0.01" min="0" name="stamp_duty" value="{{ old('stamp_duty', $purchase?->stamp_duty ?? 0) }}">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Registration charges</span>
                                        <input class="field-input" type="number" step="0.01" min="0" name="registration_charges" value="{{ old('registration_charges', $purchase?->registration_charges ?? 0) }}">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Other acquisition costs</span>
                                        <input class="field-input" type="number" step="0.01" min="0" name="other_acquisition_costs" value="{{ old('other_acquisition_costs', $purchase?->other_acquisition_costs ?? 0) }}">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Seller name</span>
                                        <input class="field-input" type="text" name="seller_name" value="{{ old('seller_name', $purchase?->seller_name) }}">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Seller contact</span>
                                        <input class="field-input" type="text" name="seller_contact" value="{{ old('seller_contact', $purchase?->seller_contact) }}">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Notes</span>
                                        <textarea class="field-input" name="notes" rows="3">{{ old('notes', $purchase?->notes) }}</textarea>
                                    </label>
                                    <button class="btn btn-solid" type="submit">Save purchase</button>
                                </form>
                            </article>

                            <article class="security-card dashboard-panel">
                                <div class="dashboard-panel-head">
                                    <div>
                                        <p class="row-label">Loan input</p>
                                        <h3 class="dashboard-panel-title">Save loan and log EMI</h3>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('properties.finance.loan.store', $property) }}">
                                    @csrf
                                    <label class="field-group">
                                        <span class="field-label">Lender name</span>
                                        <input class="field-input" type="text" name="lender_name" value="{{ old('lender_name', $loan?->lender_name) }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Loan amount</span>
                                        <input class="field-input" type="number" step="0.01" min="0" name="loan_amount" value="{{ old('loan_amount', $loan?->loan_amount) }}" required>
                                    </label>
                                    <div class="two-up-grid">
                                        <label class="field-group">
                                            <span class="field-label">Interest rate %</span>
                                            <input class="field-input" type="number" step="0.001" min="0" name="interest_rate" value="{{ old('interest_rate', $loan?->interest_rate ?? 0) }}" required>
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">Rate type</span>
                                            <select class="field-input" name="interest_rate_type" required>
                                                <option value="fixed" @selected(old('interest_rate_type', $loan?->interest_rate_type ?? 'fixed') === 'fixed')>Fixed</option>
                                                <option value="floating" @selected(old('interest_rate_type', $loan?->interest_rate_type) === 'floating')>Floating</option>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="two-up-grid">
                                        <label class="field-group">
                                            <span class="field-label">Loan start date</span>
                                            <input class="field-input" type="date" name="loan_start_date" value="{{ old('loan_start_date', $loan?->loan_start_date?->toDateString()) }}" required>
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">Tenure months</span>
                                            <input class="field-input" type="number" min="1" name="tenure_months" value="{{ old('tenure_months', $loan?->tenure_months) }}" required>
                                        </label>
                                    </div>
                                    <div class="two-up-grid">
                                        <label class="field-group">
                                            <span class="field-label">EMI amount</span>
                                            <input class="field-input" type="number" step="0.01" min="0.01" name="emi_amount" value="{{ old('emi_amount', $loan?->emi_amount) }}" required>
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">EMI due day</span>
                                            <input class="field-input" type="number" min="1" max="28" name="emi_due_day" value="{{ old('emi_due_day', $loan?->emi_due_day ?? 5) }}" required>
                                        </label>
                                    </div>
                                    <label class="field-group">
                                        <span class="field-label">Notes</span>
                                        <textarea class="field-input" name="notes" rows="3">{{ old('notes', $loan?->notes) }}</textarea>
                                    </label>
                                    <button class="btn btn-solid" type="submit">Save loan</button>
                                </form>

                                @if ($loan)
                                    <form method="POST" action="{{ route('properties.finance.loan.emis.store', [$property, $loan]) }}" style="margin-top: 1rem;">
                                        @csrf
                                        <label class="field-group">
                                            <span class="field-label">EMI paid amount</span>
                                            <input class="field-input" type="number" step="0.01" min="0.01" name="amount_paid" required>
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">Date paid</span>
                                            <input class="field-input" type="date" name="date_paid" value="{{ now()->toDateString() }}" required>
                                        </label>
                                        <div class="two-up-grid">
                                            <label class="field-group">
                                                <span class="field-label">Principal component</span>
                                                <input class="field-input" type="number" step="0.01" min="0" name="principal_component" required>
                                            </label>
                                            <label class="field-group">
                                                <span class="field-label">Interest component</span>
                                                <input class="field-input" type="number" step="0.01" min="0" name="interest_component" required>
                                            </label>
                                        </div>
                                        <label class="field-group">
                                            <span class="field-label">Outstanding balance</span>
                                            <input class="field-input" type="number" step="0.01" min="0" name="outstanding_balance" required>
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">Notes</span>
                                            <textarea class="field-input" name="notes" rows="2"></textarea>
                                        </label>
                                        <button class="btn btn-solid" type="submit">Log EMI payment</button>
                                    </form>
                                @endif
                            </article>
                        @else
                            <article class="security-card dashboard-panel">
                                <p class="security-empty">Read-only visibility is enabled for your role in this workspace.</p>
                            </article>
                        @endcan
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection