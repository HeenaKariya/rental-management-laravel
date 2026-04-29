@csrf

@if ($errors->any())
    <div class="table-card" style="margin-bottom: 1.25rem;">
        <div class="pending-card">
            @foreach ($errors->all() as $error)
                <div class="pending-row"><span>{{ $error }}</span></div>
            @endforeach
        </div>
    </div>
@endif

@if (! empty($vacancyGapContext))
    <div class="table-card" style="margin-bottom: 1.25rem;">
        <div class="pending-card">
            <div class="pending-row">
                <span>A previous tenant vacated this unit on {{ $vacancyGapContext['vacationDate'] }}. A Rent Return for {{ $vacancyGapContext['previousLease']->tenant->full_name }} is {{ $vacancyGapContext['rentReturnStatusLabel'] }}. You may proceed with this lease without processing it first.</span>
            </div>
            <div class="pending-row">
                <a href="{{ $vacancyGapContext['quickActionUrl'] }}">Open prior rent return</a>
            </div>
        </div>
    </div>
@endif

<div class="form-layout-stack">
    <section class="form-section-card">
        <div class="form-section-head">
            <div>
                <p class="page-kicker">Lease identity</p>
                <h2 class="form-section-title">Tenant and unit binding</h2>
                <p class="form-section-copy">Bind the lease to a visible unit and tenant before rent and deposit workflows attach to it.</p>
            </div>
        </div>

        <div class="form-card">
            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Unit</span>
                    <select class="field-input" name="unit_id" required>
                        <option value="">Select unit</option>
                        @foreach ($leaseUnits as $leaseUnit)
                            <option value="{{ $leaseUnit->id }}" @selected((int) old('unit_id', $lease->unit_id) === $leaseUnit->id)>{{ $leaseUnit->property->title }} · {{ $leaseUnit->unit_number }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group">
                    <span class="field-label">Tenant</span>
                    <select class="field-input" name="tenant_id" required>
                        <option value="">Select tenant</option>
                        @foreach ($tenantOptions as $tenantOption)
                            <option value="{{ $tenantOption->id }}" @selected((int) old('tenant_id', $lease->tenant_id) === $tenantOption->id)>{{ $tenantOption->full_name }} · {{ $tenantOption->unit->unit_number }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Start date</span>
                    <input class="field-input" type="date" name="start_on" value="{{ old('start_on', $lease->start_on?->format('Y-m-d')) }}" required>
                </label>

                <label class="field-group">
                    <span class="field-label">End date</span>
                    <input class="field-input" type="date" name="end_on" value="{{ old('end_on', $lease->end_on?->format('Y-m-d')) }}" required>
                </label>
            </div>
        </div>
    </section>

    <section class="form-section-card">
        <div class="form-section-head">
            <div>
                <p class="page-kicker">Commercials</p>
                <h2 class="form-section-title">Rent and billing</h2>
                <p class="form-section-copy">Capture the core commercial values needed before ledgers and instalments are introduced.</p>
            </div>
        </div>

        <div class="form-card">
            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Rent amount</span>
                    <input class="field-input" type="number" min="0" step="0.01" name="rent_amount" value="{{ old('rent_amount', $lease->rent_amount) }}" required>
                </label>

                <label class="field-group">
                    <span class="field-label">Billing day</span>
                    <input class="field-input" type="number" min="1" max="28" name="billing_day" value="{{ old('billing_day', $lease->billing_day ?: 1) }}" required>
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Status</span>
                    <select class="field-input" name="status" required>
                        @foreach ($statusOptions as $statusOption)
                            <option value="{{ $statusOption }}" @selected(old('status', $lease->status ?: 'draft') === $statusOption)>{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group">
                    <span class="field-label">Grace period</span>
                    <input class="field-input" type="number" min="0" max="31" name="grace_period_days" value="{{ old('grace_period_days', $lease->grace_period_days ?? 5) }}" required>
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Late fee mode</span>
                    <select class="field-input" name="late_fee_mode" required>
                        <option value="fixed" @selected(old('late_fee_mode', $lease->late_fee_mode ?? 'fixed') === 'fixed')>Fixed amount</option>
                        <option value="percentage" @selected(old('late_fee_mode', $lease->late_fee_mode) === 'percentage')>Percentage of outstanding</option>
                    </select>
                </label>

                <label class="field-group">
                    <span class="field-label">Late fee value</span>
                    <input class="field-input" type="number" min="0" step="0.01" name="late_fee_value" value="{{ old('late_fee_value', $lease->late_fee_value ?? 0) }}" required>
                </label>
            </div>

            <label class="field-group">
                <span class="field-label">Notes</span>
                <textarea class="field-input" name="notes" rows="5">{{ old('notes', $lease->notes) }}</textarea>
            </label>
        </div>
    </section>
</div>

<div class="btn-strip" style="margin-top: 1.5rem;">
    <button class="btn btn-solid" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-ghost" href="{{ $cancelUrl }}">Cancel</a>
</div>