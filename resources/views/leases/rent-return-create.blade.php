@extends('layouts.app', ['title' => 'Process Rent Return | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Rent return</p>
                        <h1 class="page-title">Process Rent Return</h1>
                        <p class="page-description">Prepare the pro-rata rent return for {{ $lease->tenant->full_name }} at {{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('leases.show', $lease) }}">Back to lease</a>
                    </div>
                </section>

                <section class="row g-3">
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Vacation date</p>
                        <h2 class="stat-value">{{ $draft['vacation_date']->format('M j, Y') }}</h2>
                        <p class="stat-meta"><span>from termination record</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Paid through</p>
                        <h2 class="stat-value">{{ $draft['last_paid_through_date']?->format('M j, Y') ?: 'Not detected' }}</h2>
                        <p class="stat-meta"><span>latest fully-paid month</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Unused days</p>
                        <h2 class="stat-value">{{ $draft['unused_days'] }}</h2>
                        <p class="stat-meta"><span>billing month has {{ $draft['billing_month_days'] }} days</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Suggested return</p>
                        <h2 class="stat-value">{{ number_format((float) $draft['suggested_amount'], 2) }}</h2>
                        <p class="stat-meta"><span>daily rate {{ number_format((float) $draft['daily_rate'], 4) }}</span></p>
                    </article>
                </section>

                <section class="row g-3">
                    <div class="col-12 col-xl-8 d-flex flex-column gap-3">
                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Calculation</p>
                                    <h3 class="dashboard-panel-title">Review and save the initiated return</h3>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('leases.rent-return.store', $lease) }}">
                                @csrf
                                <div class="two-up-grid">
                                    <label class="field-group">
                                        <span class="field-label">Vacation date</span>
                                        <input class="field-input" type="date" name="vacation_date" value="{{ old('vacation_date', $draft['vacation_date']->toDateString()) }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Last paid-through date</span>
                                        <input class="field-input" type="date" name="last_paid_through_date" value="{{ old('last_paid_through_date', $draft['last_paid_through_date']?->toDateString()) }}">
                                    </label>
                                </div>

                                <div class="two-up-grid">
                                    <label class="field-group">
                                        <span class="field-label">Monthly rent amount</span>
                                        <input class="field-input" type="number" min="0" step="0.01" name="monthly_rent_amount" value="{{ old('monthly_rent_amount', number_format((float) $draft['monthly_rent_amount'], 2, '.', '')) }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Billing month days</span>
                                        <input class="field-input" type="number" min="28" max="31" name="billing_month_days" value="{{ old('billing_month_days', $draft['billing_month_days']) }}" required>
                                    </label>
                                </div>

                                <label class="field-group">
                                    <span class="field-label">Notes</span>
                                    <textarea class="field-input" name="notes" rows="4">{{ old('notes') }}</textarea>
                                </label>

                                <button class="btn btn-primary" type="submit">Save initiated return</button>
                            </form>
                        </article>
                    </div>

                    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Context</p>
                                    <h3 class="dashboard-panel-title">Closure snapshot</h3>
                                </div>
                            </div>

                            <div class="field-group">
                                <span class="field-label">Outstanding arrears</span>
                                <div class="field-input">{{ number_format((float) $draft['outstanding_arrears'], 2) }}</div>
                            </div>

                            <div class="field-group">
                                <span class="field-label">Deposit status</span>
                                <div class="field-input">Handled separately through the deposit ledger.</div>
                            </div>

                            @if ((float) $draft['suggested_amount'] <= 0)
                                <p class="security-empty">No overpayment was detected from the current ledger history. You can still initiate a manual return by editing the values above.</p>
                            @else
                                <p class="security-empty">The suggested amount is based on the latest fully-paid month and remains editable before confirmation.</p>
                            @endif
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection