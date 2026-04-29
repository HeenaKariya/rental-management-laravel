@extends('layouts.app', ['title' => 'Rent Return | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Rent return</p>
                        <h1 class="page-title">{{ $lease->lease_number }}</h1>
                        <p class="page-description">Rent return record for {{ $lease->tenant->full_name }} at {{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('leases.show', $lease) }}">Back to lease</a>
                        @if ($rentReturn->canDownloadSummary())
                            <a class="btn btn-ghost" href="{{ route('leases.rent-return.summary.download', [$lease, $rentReturn]) }}">Download summary</a>
                        @endif
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Status</p>
                        <h2 class="stat-value">{{ str($rentReturn->status)->replace('_', ' ')->title() }}</h2>
                        <p class="stat-meta"><span>initiated {{ $rentReturn->initiated_at?->format('M j, Y g:i A') }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Suggested</p>
                        <h2 class="stat-value">{{ number_format((float) $rentReturn->suggested_amount, 2) }}</h2>
                        <p class="stat-meta"><span>{{ $rentReturn->unused_days }} unused days at {{ number_format((float) $rentReturn->daily_rate, 4) }}/day</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Confirmed</p>
                        <h2 class="stat-value">{{ $rentReturn->confirmed_amount !== null ? number_format((float) $rentReturn->confirmed_amount, 2) : 'Pending' }}</h2>
                        <p class="stat-meta"><span>{{ $rentReturn->override_reason ? 'Override logged' : 'No override' }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Settlement</p>
                        <h2 class="stat-value">{{ $rentReturn->settlement_method ? str($rentReturn->settlement_method)->replace('_', ' ')->title() : 'Not recorded' }}</h2>
                        <p class="stat-meta"><span>{{ $rentReturn->ledger_posted ? 'Ledger posting requested' : 'Lease record only' }}</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="form-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Calculation</p>
                                    <h3 class="dashboard-panel-title">Return summary</h3>
                                </div>
                            </div>

                            <div class="two-up-grid">
                                <div class="field-group">
                                    <span class="field-label">Vacation date</span>
                                    <div class="field-input">{{ $rentReturn->vacation_date->format('M j, Y') }}</div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Last paid-through date</span>
                                    <div class="field-input">{{ $rentReturn->last_paid_through_date?->format('M j, Y') ?: 'Not recorded' }}</div>
                                </div>
                            </div>

                            <div class="two-up-grid">
                                <div class="field-group">
                                    <span class="field-label">Billing month</span>
                                    <div class="field-input">{{ $rentReturn->billing_month->format('F Y') }}</div>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Unused days</span>
                                    <div class="field-input">{{ $rentReturn->unused_days }}</div>
                                </div>
                            </div>

                            <div class="field-group">
                                <span class="field-label">Notes</span>
                                <div class="field-input">{{ $rentReturn->notes ?: 'No notes recorded.' }}</div>
                            </div>

                            @if ($rentReturn->override_reason)
                                <div class="field-group">
                                    <span class="field-label">Override reason</span>
                                    <div class="field-input">{{ $rentReturn->override_reason }}</div>
                                </div>
                            @endif

                            @if ($rentReturn->settlement_details || $rentReturn->settlement_reference || $rentReturn->settlement_date)
                                <div class="field-group">
                                    <span class="field-label">Settlement details</span>
                                    <div class="field-input">
                                        {{ $rentReturn->settlement_details ?: 'No additional detail recorded.' }}
                                        @if ($rentReturn->settlement_reference)
                                            <br>Reference: {{ $rentReturn->settlement_reference }}
                                        @endif
                                        @if ($rentReturn->settlement_date)
                                            <br>Date: {{ $rentReturn->settlement_date->format('M j, Y') }}
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Actions</p>
                                    <h3 class="dashboard-panel-title">{{ $user?->hasRole('tenant') ? 'Read-only summary' : 'Confirm or settle' }}</h3>
                                </div>
                            </div>

                            @can('update', $lease)
                                <form method="POST" action="{{ route('leases.rent-return.update', [$lease, $rentReturn]) }}">
                                    @csrf
                                    @method('PATCH')

                                    <label class="field-group">
                                        <span class="field-label">Action</span>
                                        <select class="field-input" name="action" required>
                                            <option value="confirm">Confirm only</option>
                                            <option value="settle">Confirm and settle</option>
                                            <option value="pending">Mark pending settlement</option>
                                            <option value="waive">Waive / write-off</option>
                                        </select>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">Confirmed amount</span>
                                        <input class="field-input" type="number" min="0" step="0.01" name="confirmed_amount" value="{{ old('confirmed_amount', $rentReturn->confirmed_amount ?? $rentReturn->suggested_amount) }}" required>
                                    </label>

                                    <div class="two-up-grid">
                                        <label class="field-group">
                                            <span class="field-label">Vacation date</span>
                                            <input class="field-input" type="date" name="vacation_date" value="{{ old('vacation_date', $rentReturn->vacation_date->toDateString()) }}" required>
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">Paid-through date</span>
                                            <input class="field-input" type="date" name="last_paid_through_date" value="{{ old('last_paid_through_date', $rentReturn->last_paid_through_date?->toDateString()) }}">
                                        </label>
                                    </div>

                                    <div class="two-up-grid">
                                        <label class="field-group">
                                            <span class="field-label">Monthly rent amount</span>
                                            <input class="field-input" type="number" min="0" step="0.01" name="monthly_rent_amount" value="{{ old('monthly_rent_amount', number_format((float) $lease->rent_amount, 2, '.', '')) }}" required>
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">Billing month days</span>
                                            <input class="field-input" type="number" min="28" max="31" name="billing_month_days" value="{{ old('billing_month_days', $rentReturn->billing_month->daysInMonth) }}" required>
                                        </label>
                                    </div>

                                    <label class="field-group">
                                        <span class="field-label">Override reason</span>
                                        <textarea class="field-input" name="override_reason" rows="3">{{ old('override_reason', $rentReturn->override_reason) }}</textarea>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">Settlement method</span>
                                        <select class="field-input" name="settlement_method">
                                            <option value="">Choose when settling</option>
                                            @foreach ($settlementMethods as $settlementMethod)
                                                <option value="{{ $settlementMethod }}" @selected(old('settlement_method', $rentReturn->settlement_method) === $settlementMethod)>
                                                    {{ str($settlementMethod)->replace('_', ' ')->title() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <div class="two-up-grid">
                                        <label class="field-group">
                                            <span class="field-label">Settlement amount</span>
                                            <input class="field-input" type="number" min="0" step="0.01" name="settlement_amount" value="{{ old('settlement_amount', $rentReturn->settlement_amount) }}">
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">Settlement date</span>
                                            <input class="field-input" type="date" name="settlement_date" value="{{ old('settlement_date', $rentReturn->settlement_date?->toDateString()) }}">
                                        </label>
                                    </div>

                                    <label class="field-group">
                                        <span class="field-label">Settlement reference</span>
                                        <input class="field-input" type="text" name="settlement_reference" value="{{ old('settlement_reference', $rentReturn->settlement_reference) }}">
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">Settlement details</span>
                                        <textarea class="field-input" name="settlement_details" rows="3">{{ old('settlement_details', $rentReturn->settlement_details) }}</textarea>
                                    </label>

                                    <label class="checkbox-row">
                                        <input type="checkbox" name="ledger_posted" value="1" @checked(old('ledger_posted', $rentReturn->ledger_posted))>
                                        <span>Post this rent return to the property financial ledger</span>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">Notes</span>
                                        <textarea class="field-input" name="notes" rows="3">{{ old('notes', $rentReturn->notes) }}</textarea>
                                    </label>

                                    <button class="btn btn-solid" type="submit">Save status update</button>
                                </form>
                            @else
                                <p class="security-empty">This record is visible from the tenant portal in read-only mode.</p>
                            @endcan
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection