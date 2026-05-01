@extends('layouts.app', ['title' => 'New Maintenance Request | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Operations</p>
                        <h1 class="page-title">Create maintenance request</h1>
                        <p class="page-description">Capture tenant-reported issues and dispatch for resolution.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('maintenance.index') }}">Back to requests</a>
                    </div>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <form method="POST" action="{{ route('maintenance.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Unit</span>
                                <select class="field-input" name="unit_id" required>
                                    <option value="">Select unit</option>
                                    @foreach ($unitOptions as $unit)
                                        <option value="{{ $unit->id }}" @selected((int) old('unit_id') === $unit->id)>
                                            {{ $unit->property?->title }} · {{ $unit->unit_number }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            @if (! $user->hasRole('tenant'))
                                <label class="field-group">
                                    <span class="field-label">Tenant (optional)</span>
                                    <select class="field-input" name="tenant_id">
                                        <option value="">No specific tenant</option>
                                        @foreach ($tenants as $tenant)
                                            <option value="{{ $tenant->id }}" @selected((int) old('tenant_id') === $tenant->id)>
                                                {{ $tenant->full_name }} · {{ $tenant->unit?->unit_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            <label class="field-group">
                                <span class="field-label">Title</span>
                                <input class="field-input" type="text" name="title" value="{{ old('title') }}" required>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Category</span>
                                <select class="field-input" name="category" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected(old('category', 'other') === $category)>{{ str($category)->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Priority</span>
                                <select class="field-input" name="priority" required>
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ str($priority)->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Photos (up to 5)</span>
                                <input class="field-input" type="file" name="photos[]" accept=".jpg,.jpeg,.png" multiple>
                            </label>
                        </div>

                        <label class="field-group">
                            <span class="field-label">Description</span>
                            <textarea class="field-input" name="description" rows="5" required>{{ old('description') }}</textarea>
                        </label>

                        <div class="btn-strip" style="margin-top: 0.75rem;">
                            <button class="btn btn-primary" type="submit">Create request</button>
                        </div>
                    </form>
                </article>
            </div>
        </div>
    </div>
@endsection
