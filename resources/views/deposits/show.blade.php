@extends('layouts.app', ['title' => 'Deposit Detail | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Deposit detail</p>
                        <h1 class="page-title">{{ $deposit->lease->lease_number }}</h1>
                        <p class="page-description">Deposit sub-ledger for {{ $deposit->lease->tenant->full_name }} at {{ $deposit->lease->unit->property->title }} · {{ $deposit->lease->unit->unit_number }}.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ $user?->hasRole('tenant') ? route('dashboard') : route('deposits.index') }}">{{ $user?->hasRole('tenant') ? 'Back to portal' : 'Back to deposits' }}</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Expected</p>
                        <h2 class="stat-value">{{ number_format((float) $deposit->expected_amount, 2) }}</h2>
                        <p class="stat-meta"><span>target security amount</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Balance</p>
                        <h2 class="stat-value">{{ number_format((float) $deposit->current_balance, 2) }}</h2>
                        <p class="stat-meta"><span>{{ $deposit->reconciles() ? 'reconciled' : 'check ledger' }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Collected</p>
                        <h2 class="stat-value">{{ number_format((float) $deposit->collected_total, 2) }}</h2>
                        <p class="stat-meta"><span>plus {{ number_format((float) $deposit->top_up_total, 2) }} top-up</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Released</p>
                        <h2 class="stat-value">{{ number_format((float) $deposit->refunded_total + (float) $deposit->deducted_total + (float) $deposit->forfeited_total, 2) }}</h2>
                        <p class="stat-meta"><span>{{ str($deposit->status)->replace('_', ' ')->title() }}</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Ledger timeline</p>
                                    <h3 class="dashboard-panel-title">Deposit entries</h3>
                                </div>
                            </div>

                            @if ($deposit->entries->isEmpty())
                                <p class="security-empty">No deposit entries have been posted yet.</p>
                            @else
                                <div class="table-head">
                                    <span>Type</span>
                                    <span>Amount</span>
                                    <span>Notes</span>
                                    <span>Occurred</span>
                                </div>
                                @foreach ($deposit->entries as $entry)
                                    <div class="table-row">
                                        <div class="muted-text">{{ str($entry->entry_type)->replace('_', ' ')->title() }}</div>
                                        <div class="tenant-name">{{ number_format((float) $entry->amount, 2) }}</div>
                                        <div class="muted-text">{{ $entry->notes ?: 'No notes' }}</div>
                                        <div class="muted-text">{{ $entry->occurred_at?->format('M j, Y g:i A') }}</div>
                                    </div>
                                @endforeach
                            @endif
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Post entry</p>
                                    <h3 class="dashboard-panel-title">Update the sub-ledger</h3>
                                </div>
                            </div>

                            @can('postEntry', $deposit)
                                <form method="POST" action="{{ route('deposits.entries.store', $deposit) }}">
                                    @csrf
                                    <label class="field-group">
                                        <span class="field-label">Entry type</span>
                                        <select class="field-input" name="entry_type" required>
                                            @foreach ($entryTypeOptions as $entryTypeOption)
                                                <option value="{{ $entryTypeOption }}">{{ str($entryTypeOption)->replace('_', ' ')->title() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Amount</span>
                                        <input class="field-input" type="number" min="0.01" step="0.01" name="amount" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Notes</span>
                                        <textarea class="field-input" name="notes" rows="3"></textarea>
                                    </label>
                                    <button class="btn btn-solid" type="submit">Post entry</button>
                                </form>
                            @else
                                <p class="security-empty">This deposit ledger is visible in read-only mode from the tenant portal.</p>
                            @endcan
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection