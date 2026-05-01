@csrf

@if ($errors->any())
    <div class="card border-0 shadow-sm" style="margin-bottom: 1.25rem;">
        <div class="card border-0 shadow-sm">
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
                <p class="page-kicker">Unit identity</p>
                <h2 class="form-section-title">Inventory details</h2>
                <p class="form-section-copy">Tie the unit to a visible property and define its inventory and occupancy baseline.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Property</span>
                    <select class="field-input" name="property_id" required>
                        <option value="">Select property</option>
                        @foreach ($propertyOptions as $propertyOption)
                            <option value="{{ $propertyOption->id }}" @selected((int) old('property_id', $unit->property_id) === $propertyOption->id)>{{ $propertyOption->title }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group">
                    <span class="field-label">Unit number</span>
                    <input class="field-input" type="text" name="unit_number" value="{{ old('unit_number', $unit->unit_number) }}" required>
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Floor</span>
                    <input class="field-input" type="text" name="floor" value="{{ old('floor', $unit->floor) }}">
                </label>

                <label class="field-group">
                    <span class="field-label">Occupancy status</span>
                    <select class="field-input" name="occupancy_status" required>
                        @foreach ($occupancyOptions as $occupancyOption)
                            <option value="{{ $occupancyOption }}" @selected(old('occupancy_status', $unit->occupancy_status ?: 'vacant') === $occupancyOption)>{{ str($occupancyOption)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </section>

    <section class="form-section-card">
        <div class="form-section-head">
            <div>
                <p class="page-kicker">Layout</p>
                <h2 class="form-section-title">Space profile</h2>
                <p class="form-section-copy">Capture the inventory dimensions needed before tenants and leases attach to this unit.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Bedrooms</span>
                    <input class="field-input" type="number" min="0" name="bedrooms" value="{{ old('bedrooms', $unit->bedrooms) }}">
                </label>

                <label class="field-group">
                    <span class="field-label">Bathrooms</span>
                    <input class="field-input" type="number" min="0" step="0.5" name="bathrooms" value="{{ old('bathrooms', $unit->bathrooms) }}">
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Area</span>
                    <input class="field-input" type="number" min="0" step="0.01" name="area" value="{{ old('area', $unit->area) }}">
                </label>

                <label class="field-group">
                    <span class="field-label">Area unit</span>
                    <select class="field-input" name="area_unit">
                        @foreach (['sqft' => 'Sq. ft.', 'sqm' => 'Sq. m.'] as $areaUnitValue => $areaUnitLabel)
                            <option value="{{ $areaUnitValue }}" @selected(old('area_unit', $unit->area_unit ?: 'sqft') === $areaUnitValue)>{{ $areaUnitLabel }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Vacant since</span>
                    <input class="field-input" type="date" name="vacant_since" value="{{ old('vacant_since', $unit->vacant_since?->format('Y-m-d')) }}">
                </label>
            </div>

            <label class="field-group">
                <span class="field-label">Notes</span>
                <textarea class="field-input" name="notes" rows="5">{{ old('notes', $unit->notes) }}</textarea>
            </label>
        </div>
    </section>
</div>

<div class="btn-strip" style="margin-top: 1.5rem;">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ $cancelUrl }}">Cancel</a>
</div>