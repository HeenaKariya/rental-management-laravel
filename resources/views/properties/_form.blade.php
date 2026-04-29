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
                <p class="page-kicker">Basic info</p>
                <h2 class="form-section-title">Property identity</h2>
                <p class="form-section-copy">Define the title, type, lifecycle stage, and the main notes for this property.</p>
            </div>
        </div>

        <div class="form-card">
            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Property title</span>
                    <input class="field-input" type="text" name="title" value="{{ old('title', $property->title) }}" required>
                </label>

                <label class="field-group">
                    <span class="field-label">Property type</span>
                    <select class="field-input" name="type" required>
                        @foreach ($propertyTypes as $propertyType)
                            <option value="{{ $propertyType }}" @selected(old('type', $property->type) === $propertyType)>
                                {{ str($propertyType)->replace('_', ' ')->title() }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Lifecycle stage</span>
                    <select class="field-input" name="lifecycle_stage" required>
                        @foreach ($stageOptions as $stageOption)
                            <option value="{{ $stageOption }}" @selected(old('lifecycle_stage', $property->lifecycle_stage ?: 'draft') === $stageOption)>
                                {{ str($stageOption)->replace('_', ' ')->title() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="field-group">
                    <span class="field-label">Area</span>
                    <div class="field-inline-grid">
                        <input class="field-input" type="number" step="0.01" min="0" name="area" value="{{ old('area', $property->area) }}">
                        <select class="field-input" name="area_unit">
                            @foreach (['sqft' => 'Sq. ft.', 'sqm' => 'Sq. m.'] as $areaUnitValue => $areaUnitLabel)
                                <option value="{{ $areaUnitValue }}" @selected(old('area_unit', $property->area_unit ?: 'sqft') === $areaUnitValue)>
                                    {{ $areaUnitLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </label>
            </div>

            <label class="field-group">
                <span class="field-label">Notes</span>
                <textarea class="field-input" name="description" rows="5">{{ old('description', $property->description) }}</textarea>
            </label>
        </div>
    </section>

    <section class="form-section-card">
        <div class="form-section-head">
            <div>
                <p class="page-kicker">Location</p>
                <h2 class="form-section-title">Address details</h2>
                <p class="form-section-copy">Store the address exactly as it should appear across listings, records, and later documents.</p>
            </div>
        </div>

        <div class="form-card">
            <label class="field-group">
                <span class="field-label">Street address</span>
                <input class="field-input" type="text" name="street_address" value="{{ old('street_address', $property->street_address) }}" required>
            </label>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">City</span>
                    <input class="field-input" type="text" name="city" value="{{ old('city', $property->city) }}" required>
                </label>

                <label class="field-group">
                    <span class="field-label">State</span>
                    <input class="field-input" type="text" name="state" value="{{ old('state', $property->state) }}" required>
                </label>
            </div>

            <div class="two-up-grid">
                <label class="field-group">
                    <span class="field-label">Postal code</span>
                    <input class="field-input" type="text" name="postal_code" value="{{ old('postal_code', $property->postal_code) }}" required>
                </label>

                <label class="field-group">
                    <span class="field-label">Country</span>
                    <input class="field-input" type="text" name="country" value="{{ old('country', $property->country ?: 'India') }}" required>
                </label>
            </div>
        </div>
    </section>

    <section class="form-section-card">
        <div class="form-section-head">
            <div>
                <p class="page-kicker">Assignment</p>
                <h2 class="form-section-title">Ownership and media</h2>
                <p class="form-section-copy">Assign the responsible manager and upload the visual assets for this property.</p>
            </div>
        </div>

        <div class="form-card">
            @if ($user->hasRole('super_admin'))
                <label class="field-group">
                    <span class="field-label">Assigned manager</span>
                    <select class="field-input" name="assigned_manager_id">
                        <option value="">No manager assigned</option>
                        @foreach ($managerOptions as $managerOption)
                            <option value="{{ $managerOption->id }}" @selected((int) old('assigned_manager_id', optional($property->managers->first())->id) === $managerOption->id)>
                                {{ $managerOption->name }} · {{ $managerOption->email }}
                            </option>
                        @endforeach
                    </select>
                    <span class="field-hint">Managers only see properties assigned to them.</span>
                </label>
            @endif

            <label class="field-group">
                <span class="field-label">Property photos</span>
                <input class="field-input" type="file" name="photos[]" accept="image/*" multiple>
                <span class="field-hint">Upload a cover and gallery images. The first image becomes cover unless you choose an existing one below.</span>
            </label>

            @if ($property->exists && $property->photos->isNotEmpty())
                <div class="form-photo-stack">
                    @foreach ($property->photos as $photo)
                        <div class="form-photo-row">
                            <div>
                                <p class="tenant-name">Photo #{{ $photo->id }}</p>
                                <p class="tenant-unit">{{ $photo->is_cover ? 'Current cover image' : 'Gallery image' }}</p>
                            </div>

                            <label class="field-group form-photo-order-field">
                                <span class="field-label">Order</span>
                                <input class="field-input" type="number" min="1" name="photo_orders[{{ $photo->id }}]" value="{{ old('photo_orders.'.$photo->id, $photo->sort_order) }}">
                            </label>
                        </div>
                    @endforeach
                </div>

                <label class="field-group">
                    <span class="field-label">Cover photo</span>
                    <select class="field-input" name="cover_photo_id">
                        @foreach ($property->photos as $photo)
                            <option value="{{ $photo->id }}" @selected((int) old('cover_photo_id', optional($property->photos->firstWhere('is_cover', true))->id) === $photo->id)>
                                Photo #{{ $photo->id }}{{ $photo->is_cover ? ' · Current cover' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
    </section>
</div>

<div class="btn-strip" style="margin-top: 1.5rem;">
    <button class="btn btn-solid" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-ghost" href="{{ $cancelUrl }}">Cancel</a>
</div>
