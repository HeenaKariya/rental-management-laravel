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

<div class="form-layout-stack">
    <section class="form-section-card">
        <div class="form-section-head">
            <div>
                <p class="page-kicker">Tenant identity</p>
                <h2 class="form-section-title">Core contact profile</h2>
                <p class="form-section-copy">Attach the tenant to a visible unit and capture the base profile used by later lease and deposit workflows.</p>
            </div>
        </div>

        <div class="form-card">
            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Unit</span>
                    <select class="field-input" name="unit_id" required>
                        <option value="">Select unit</option>
                        @foreach ($tenantUnits as $tenantUnit)
                            <option value="{{ $tenantUnit->id }}" @selected((int) old('unit_id', $tenant->unit_id) === $tenantUnit->id)>{{ $tenantUnit->property->title }} · {{ $tenantUnit->unit_number }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group">
                    <span class="field-label">Full name</span>
                    <input class="field-input" type="text" name="full_name" value="{{ old('full_name', $tenant->full_name) }}" required>
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Email</span>
                    <input class="field-input" type="email" name="email" value="{{ old('email', $tenant->email) }}">
                </label>

                <label class="field-group">
                    <span class="field-label">Phone</span>
                    <input class="field-input" type="text" name="phone" value="{{ old('phone', $tenant->phone) }}">
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Tenant status</span>
                    <select class="field-input" name="status" required>
                        @foreach ($statusOptions as $statusOption)
                            <option value="{{ $statusOption }}" @selected(old('status', $tenant->status ?: 'prospect') === $statusOption)>{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group">
                    <span class="field-label">KYC status</span>
                    <select class="field-input" name="kyc_status" required>
                        @foreach ($kycStatusOptions as $kycStatusOption)
                            <option value="{{ $kycStatusOption }}" @selected(old('kyc_status', $tenant->kyc_status ?: 'pending') === $kycStatusOption)>{{ str($kycStatusOption)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </section>

    <section class="form-section-card">
        <div class="form-section-head">
            <div>
                <p class="page-kicker">Timeline</p>
                <h2 class="form-section-title">Occupancy dates</h2>
                <p class="form-section-copy">Track move-in and move-out milestones before leases add stricter lifecycle invariants.</p>
            </div>
        </div>

        <div class="form-card">
            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Move-in date</span>
                    <input class="field-input" type="date" name="move_in_on" value="{{ old('move_in_on', $tenant->move_in_on?->format('Y-m-d')) }}">
                </label>

                <label class="field-group">
                    <span class="field-label">Move-out date</span>
                    <input class="field-input" type="date" name="move_out_on" value="{{ old('move_out_on', $tenant->move_out_on?->format('Y-m-d')) }}">
                </label>
            </div>

            <label class="field-group">
                <span class="field-label">Notes</span>
                <textarea class="field-input" name="notes" rows="5">{{ old('notes', $tenant->notes) }}</textarea>
            </label>
        </div>
    </section>

    <section class="form-section-card">
        <div class="form-section-head">
            <div>
                <p class="page-kicker">KYC documents</p>
                <h2 class="form-section-title">Upload verification files</h2>
                <p class="form-section-copy">Store the supporting documents that later compliance and tenancy review flows will rely on.</p>
            </div>
        </div>

        <div class="form-card">
            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Document type</span>
                    <select class="field-input" name="kyc_document_type">
                        @foreach (['identity' => 'Identity proof', 'address' => 'Address proof', 'income' => 'Income proof', 'other' => 'Other'] as $documentTypeValue => $documentTypeLabel)
                            <option value="{{ $documentTypeValue }}" @selected(old('kyc_document_type', 'identity') === $documentTypeValue)>{{ $documentTypeLabel }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group">
                    <span class="field-label">Files</span>
                    <input class="field-input" type="file" name="kyc_documents[]" multiple accept=".pdf,image/*">
                </label>
            </div>

            @if ($tenant->exists && $tenant->documents->isNotEmpty())
                <div class="data-table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Type</th>
                                <th scope="col">File</th>
                                <th scope="col">Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tenant->documents as $document)
                                <tr>
                                    <td>{{ str($document->document_type)->replace('_', ' ')->title() }}</td>
                                    <td><a href="{{ $document->url() }}" target="_blank" rel="noreferrer">{{ $document->original_name }}</a></td>
                                    <td>{{ $document->uploaded_at?->format('M j, Y g:i A') ?: 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</div>

<div class="btn-strip" style="margin-top: 1.5rem;">
    <button class="btn btn-solid" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-ghost" href="{{ $cancelUrl }}">Cancel</a>
</div>