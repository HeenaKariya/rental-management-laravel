@extends('layouts.app', ['title' => 'Payment History | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 4 foundation</p>
                        <h1 class="page-title">{{ $lease->lease_number }} payment history</h1>
                        <p class="page-description">Monthly rent demand, arrears carry-forward, credits, and instalments for {{ $lease->tenant->full_name }} at {{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('leases.show', $lease) }}">Back to lease</a>
                        @can('update', $lease)
                            <a class="btn btn-solid" href="{{ route('leases.edit', $lease) }}">Edit lease billing</a>
                        @endcan
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Ledger months</p>
                        <h2 class="stat-value">{{ $lease->rentLedgers->count() }}</h2>
                        <p class="stat-meta"><span>lease timeline generated</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Open balance</p>
                        <h2 class="stat-value">{{ number_format($lease->rentLedgers->sum(fn ($ledger) => max((float) $ledger->outstanding_balance, 0)), 2) }}</h2>
                        <p class="stat-meta"><span>includes arrears and late fees</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Credits</p>
                        <h2 class="stat-value">{{ number_format($lease->rentLedgers->sum(fn ($ledger) => max(((float) $ledger->outstanding_balance) * -1, 0)), 2) }}</h2>
                        <p class="stat-meta"><span>overpayments carried forward</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Late fee rule</p>
                        <h2 class="stat-value">{{ $lease->late_fee_mode === 'percentage' ? number_format((float) $lease->late_fee_value, 2).'%' : number_format((float) $lease->late_fee_value, 2) }}</h2>
                        <p class="stat-meta"><span>{{ $lease->grace_period_days }} day grace period</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Payment timeline</p>
                                    <h3 class="dashboard-panel-title">Monthly ledger</h3>
                                </div>
                            </div>

                            @if ($lease->rentLedgers->isEmpty())
                                <p class="security-empty">No rent ledger entries are available yet for this lease.</p>
                            @else
                                @foreach ($lease->rentLedgers as $ledger)
                                    <article class="table-card" style="margin-bottom: 1rem;">
                                        <div class="dashboard-panel-head">
                                            <div>
                                                <p class="row-label">{{ $ledger->payment_month->format('F Y') }}</p>
                                                <h3 class="dashboard-panel-title">Due {{ $ledger->due_on->format('M j, Y') }}</h3>
                                            </div>
                                            <span class="badge badge-violet compact-badge">{{ str($ledger->status)->replace('_', ' ')->title() }}</span>
                                        </div>

                                        <div class="table-head">
                                            <span>Base rent</span>
                                            <span>Arrears</span>
                                            <span>Credit</span>
                                            <span>Outstanding</span>
                                        </div>
                                        <div class="table-row">
                                            <div class="muted-text">{{ number_format((float) $ledger->base_rent_amount, 2) }}</div>
                                            <div class="muted-text">{{ number_format((float) $ledger->carried_arrears, 2) }}</div>
                                            <div class="muted-text">{{ number_format((float) $ledger->credit_brought_forward, 2) }}</div>
                                            <div class="tenant-name">{{ number_format((float) $ledger->outstanding_balance, 2) }}</div>
                                        </div>

                                        <div class="table-head">
                                            <span>Total due</span>
                                            <span>Received</span>
                                            <span>Late fees</span>
                                            <span>Instalments</span>
                                        </div>
                                        <div class="table-row">
                                            <div class="muted-text">{{ number_format((float) $ledger->total_due, 2) }}</div>
                                            <div class="muted-text">{{ number_format((float) $ledger->total_received, 2) }}</div>
                                            <div class="muted-text">{{ number_format((float) $ledger->late_fee_total, 2) }}</div>
                                            <div class="muted-text">{{ $ledger->instalments->count() }}</div>
                                        </div>

                                        @if ($ledger->instalments->isNotEmpty())
                                            <div class="table-head">
                                                <span>Instalment</span>
                                                <span>Amount</span>
                                                <span>Mode</span>
                                                <span>Recorded</span>
                                                <span>Receipt</span>
                                            </div>
                                            @foreach ($ledger->instalments as $instalment)
                                                <div class="table-row">
                                                    <div>
                                                        <div class="tenant-name">Instalment {{ $instalment->instalment_number }}</div>
                                                        <div class="tenant-unit">{{ $instalment->payment_date->format('M j, Y') }}</div>
                                                    </div>
                                                    <div class="muted-text">{{ number_format((float) $instalment->amount_paid, 2) }}</div>
                                                    <div class="muted-text">{{ str($instalment->payment_mode)->replace('_', ' ')->title() }}</div>
                                                    <div class="muted-text">{{ $instalment->recorder?->name ?: 'System' }}</div>
                                                    <div><a class="btn btn-ghost btn-sm" href="{{ route('leases.payments.receipt.download', [$lease, $ledger, $instalment]) }}">Receipt PDF</a></div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </article>
                                @endforeach
                            @endif
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Record instalment</p>
                                    <h3 class="dashboard-panel-title">Apply a payment</h3>
                                </div>
                            </div>

                            @can('update', $lease)
                                <form method="POST" action="{{ route('leases.payments.instalments.store', [$lease, $lease->rentLedgers->first()]) }}" onsubmit="this.action=this.dataset.base.replace('__LEDGER__', this.querySelector('[name=rent_ledger_id]').value);" data-base="{{ route('leases.payments.instalments.store', [$lease, '__LEDGER__']) }}">
                                    @csrf
                                    <label class="field-group">
                                        <span class="field-label">Ledger month</span>
                                        <select class="field-input" name="rent_ledger_id" required>
                                            @foreach ($lease->rentLedgers as $ledgerOption)
                                                <option value="{{ $ledgerOption->id }}">{{ $ledgerOption->payment_month->format('F Y') }} · {{ str($ledgerOption->status)->replace('_', ' ')->title() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Amount paid</span>
                                        <input class="field-input" type="number" min="0.01" step="0.01" name="amount_paid" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Payment date</span>
                                        <input class="field-input" type="date" name="payment_date" value="{{ now()->toDateString() }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Payment mode</span>
                                        <select class="field-input" name="payment_mode" required>
                                            @foreach ($paymentModes as $paymentMode)
                                                <option value="{{ $paymentMode }}">{{ str($paymentMode)->replace('_', ' ')->title() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Reference</span>
                                        <input class="field-input" type="text" name="reference_number">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Late fee charged</span>
                                        <input class="field-input" type="number" min="0" step="0.01" name="late_fee_charged">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Waiver reason</span>
                                        <textarea class="field-input" name="late_fee_waiver_reason" rows="2"></textarea>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Notes</span>
                                        <textarea class="field-input" name="notes" rows="3"></textarea>
                                    </label>
                                    <button class="btn btn-solid" type="submit">Record instalment</button>
                                </form>
                            @else
                                <p class="security-empty">This payment history is visible in read-only mode from the tenant portal.</p>
                            @endcan
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection