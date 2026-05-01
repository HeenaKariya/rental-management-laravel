@extends('layouts.app', ['title' => 'Property Ledger | PropMgr'])

@section('content')
    @php
        $approvedIncome = $entries->where('entry_type', 'income')->where('status', 'approved')->sum(fn ($entry) => (float) $entry->amount);
        $approvedExpense = $entries->where('entry_type', 'expense')->where('status', 'approved')->sum(fn ($entry) => (float) $entry->amount);
        $pendingExpense = $entries->where('entry_type', 'expense')->where('status', 'pending_review')->sum(fn ($entry) => (float) $entry->amount);
    @endphp

    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 5 finance</p>
                        <h1 class="page-title">{{ $property->title }} ledger</h1>
                        <p class="page-description">Property-level income and expense entries, including flagged expense review workflow.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('properties.show', $property) }}">Back to property</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="row g-3">
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Approved income</p>
                        <h2 class="stat-value">{{ number_format((float) $approvedIncome, 2) }}</h2>
                        <p class="stat-meta"><span>ledger income total</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Approved expense</p>
                        <h2 class="stat-value">{{ number_format((float) $approvedExpense, 2) }}</h2>
                        <p class="stat-meta"><span>ledger expense total</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Net income</p>
                        <h2 class="stat-value">{{ number_format((float) ($approvedIncome - $approvedExpense), 2) }}</h2>
                        <p class="stat-meta"><span>approved entries only</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Pending expense review</p>
                        <h2 class="stat-value">{{ number_format((float) $pendingExpense, 2) }}</h2>
                        <p class="stat-meta"><span>awaiting Super Admin decision</span></p>
                    </article>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Filters</p>
                            <h3 class="dashboard-panel-title">Ledger scope</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('properties.finance.ledger.index', $property) }}">
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Entry type</span>
                                <select class="field-input" name="entry_type">
                                    <option value="">All</option>
                                    <option value="income" @selected(($filters['entry_type'] ?? null) === 'income')>Income</option>
                                    <option value="expense" @selected(($filters['entry_type'] ?? null) === 'expense')>Expense</option>
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Status</span>
                                <select class="field-input" name="status">
                                    <option value="">All</option>
                                    <option value="approved" @selected(($filters['status'] ?? null) === 'approved')>Approved</option>
                                    <option value="pending_review" @selected(($filters['status'] ?? null) === 'pending_review')>Pending review</option>
                                    <option value="rejected" @selected(($filters['status'] ?? null) === 'rejected')>Rejected</option>
                                </select>
                            </label>
                        </div>
                        <div class="btn-strip" style="margin-top: 1rem;">
                            <button class="btn btn-primary" type="submit">Apply</button>
                            <a class="btn btn-outline-secondary" href="{{ route('properties.finance.ledger.index', $property) }}">Reset</a>
                        </div>
                    </form>
                </article>

                <section class="row g-3">
                    <div class="col-12 col-xl-8 d-flex flex-column gap-3">
                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Ledger timeline</p>
                                    <h3 class="dashboard-panel-title">Income and expense entries</h3>
                                </div>
                            </div>

                            @if ($entries->isEmpty())
                                <p class="security-empty">No ledger entries are available for this filter selection.</p>
                            @else
                                <div class="">
                                    <table class="data-table data-table-compact table w-100">
                                        <thead>
                                            <tr>
                                                <th scope="col">Date</th>
                                                <th scope="col">Type</th>
                                                <th scope="col">Category</th>
                                                <th scope="col">Amount</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($entries as $entry)
                                                <tr>
                                                    <td>{{ $entry->entry_date->format('M j, Y') }}</td>
                                                    <td>{{ str($entry->entry_type)->replace('_', ' ')->title() }}</td>
                                                    <td>{{ str($entry->category)->replace('_', ' ')->title() }}</td>
                                                    <td>{{ number_format((float) $entry->amount, 2) }}</td>
                                                    <td>
                                                        <span class="badge {{ $entry->status === 'approved' ? 'badge-green' : ($entry->status 'rejected' 'badge-coral' 'badge-gold') }} compact-badge">{{ str($entry->status)->replace('_', ' ')->title() }}</span>
                                                    </td>
                                                    <td>
                                                        {{ $entry->notes ?: 'No notes' }}
                                                        @if ($entry->flagged_reason)
                                                            <br><span class="muted-text">Flag: {{ $entry->flagged_reason }}</span>
                                                        @endif
                                                        @if ($entry->review_notes)
                                                            <br><span class="muted-text">Review: {{ $entry->review_notes }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    </div>

                    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
                        @if ($canManage)
                            <article class="card border-0 shadow-sm dashboard-panel">
                                <div class="dashboard-panel-head">
                                    <div>
                                        <p class="row-label">Manual income</p>
                                        <h3 class="dashboard-panel-title">Add income entry</h3>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('properties.finance.ledger.income.store', $property) }}">
                                    @csrf
                                    <label class="field-group">
                                        <span class="field-label">Date</span>
                                        <input class="field-input" type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Category</span>
                                        <input class="field-input" type="text" name="category" placeholder="parking_charges" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Amount</span>
                                        <input class="field-input" type="number" min="0.01" step="0.01" name="amount" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Reference number</span>
                                        <input class="field-input" type="text" name="reference_number">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Notes</span>
                                        <textarea class="field-input" name="notes" rows="3"></textarea>
                                    </label>
                                    <button class="btn btn-primary" type="submit">Save income</button>
                                </form>
                            </article>

                            <article class="card border-0 shadow-sm dashboard-panel">
                                <div class="dashboard-panel-head">
                                    <div>
                                        <p class="row-label">Manual expense</p>
                                        <h3 class="dashboard-panel-title">Add expense entry</h3>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('properties.finance.ledger.expense.store', $property) }}">
                                    @csrf
                                    <label class="field-group">
                                        <span class="field-label">Date</span>
                                        <input class="field-input" type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Category</span>
                                        <select class="field-input" name="category" required>
                                            @foreach ($expenseCategories as $expenseCategory)
                                                <option value="{{ $expenseCategory }}">{{ str($expenseCategory)->replace('_', ' ')->title() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Amount</span>
                                        <input class="field-input" type="number" min="0.01" step="0.01" name="amount" required>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Vendor / payee</span>
                                        <input class="field-input" type="text" name="vendor_name">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Reference number</span>
                                        <input class="field-input" type="text" name="reference_number">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Notes</span>
                                        <textarea class="field-input" name="notes" rows="3"></textarea>
                                    </label>
                                    <button class="btn btn-primary" type="submit">Save expense</button>
                                </form>
                            </article>
                        @endif

                        @if ($user?->hasRole('super_admin'))
                            <article class="card border-0 shadow-sm dashboard-panel">
                                <div class="dashboard-panel-head">
                                    <div>
                                        <p class="row-label">Review queue</p>
                                        <h3 class="dashboard-panel-title">Pending expenses</h3>
                                    </div>
                                </div>

                                @forelse ($entries->where('entry_type', 'expense')->where('status', 'pending_review') as $entry)
                                    <form method="POST" action="{{ route('properties.finance.ledger.expense.review', [$property, $entry]) }}" style="margin-bottom: 1rem;">
                                        @csrf
                                        @method('PATCH')
                                        <p class="security-meta">{{ str($entry->category)->replace('_', ' ')->title() }} · {{ number_format((float) $entry->amount, 2) }}</p>
                                        <p class="security-empty">{{ $entry->flagged_reason }}</p>
                                        <label class="field-group">
                                            <span class="field-label">Review notes</span>
                                            <textarea class="field-input" name="review_notes" rows="2"></textarea>
                                        </label>
                                        <div class="btn-strip">
                                            <button class="btn btn-primary btn-sm" type="submit" name="action" value="approve">Approve</button>
                                            <button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="reject">Reject</button>
                                        </div>
                                    </form>
                                @empty
                                    <p class="security-empty">No pending expense review items in this property scope.</p>
                                @endforelse
                            </article>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
