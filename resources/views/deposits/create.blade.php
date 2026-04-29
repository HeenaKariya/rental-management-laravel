@extends('layouts.app', ['title' => 'Create Deposit | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Open deposit account</h1>
                        <p class="page-description">Create the lease-linked deposit account before posting collections, deductions, refunds, forfeitures, or top-ups.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('deposits.index') }}">Back to deposits</a>
                    </div>
                </section>

                @if ($errors->any())
                    <div class="table-card" style="margin-bottom: 1.25rem;">
                        <div class="pending-card">
                            @foreach ($errors->all() as $error)
                                <div class="pending-row"><span>{{ $error }}</span></div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('deposits.store') }}">
                    @csrf
                    <div class="form-layout-stack">
                        <section class="form-section-card">
                            <div class="form-section-head">
                                <div>
                                    <p class="page-kicker">Deposit identity</p>
                                    <h2 class="form-section-title">Link the ledger</h2>
                                    <p class="form-section-copy">Each lease can have one deposit account with a reconciled running balance.</p>
                                </div>
                            </div>

                            <div class="form-card">
                                <label class="field-group">
                                    <span class="field-label">Lease</span>
                                    <select class="field-input" name="lease_id" required>
                                        <option value="">Select lease</option>
                                        @foreach ($leaseOptions as $leaseOption)
                                            <option value="{{ $leaseOption->id }}" @selected((int) old('lease_id', $deposit->lease_id) === $leaseOption->id)>{{ $leaseOption->lease_number }} · {{ $leaseOption->tenant->full_name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <div class="two-up-grid">
                                    <label class="field-group">
                                        <span class="field-label">Expected amount</span>
                                        <input class="field-input" type="number" min="0" step="0.01" name="expected_amount" value="{{ old('expected_amount', $deposit->expected_amount) }}" required>
                                    </label>

                                    <label class="field-group">
                                        <span class="field-label">Initial collection</span>
                                        <input class="field-input" type="number" min="0" step="0.01" name="initial_collection" value="{{ old('initial_collection') }}">
                                    </label>
                                </div>

                                <label class="field-group">
                                    <span class="field-label">Notes</span>
                                    <textarea class="field-input" name="notes" rows="4">{{ old('notes', $deposit->notes) }}</textarea>
                                </label>
                            </div>
                        </section>
                    </div>

                    <div class="btn-strip" style="margin-top: 1.5rem;">
                        <button class="btn btn-solid" type="submit">Open deposit</button>
                        <a class="btn btn-ghost" href="{{ route('deposits.index') }}">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection