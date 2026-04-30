@extends('layouts.app', ['title' => 'Maintenance Request | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Maintenance request</p>
                        <h1 class="page-title">{{ $maintenanceRequest->title }}</h1>
                        <p class="page-description">{{ $maintenanceRequest->unit?->property?->title }} · {{ $maintenanceRequest->unit?->unit_number }}</p>
                    </div>
                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('maintenance.index') }}">Back to list</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status"><span class="badge badge-green">{{ session('status') }}</span></div>
                @endif

                <article class="table-card dashboard-panel">
                    <div class="pending-row"><span>Status</span><span>{{ str($maintenanceRequest->status)->replace('_', ' ')->title() }}</span></div>
                    <div class="pending-row"><span>Priority</span><span>{{ str($maintenanceRequest->priority)->title() }}</span></div>
                    <div class="pending-row"><span>Category</span><span>{{ str($maintenanceRequest->category)->title() }}</span></div>
                    <div class="pending-row"><span>Submitted by</span><span>{{ $maintenanceRequest->submitter?->name ?? 'System' }}</span></div>
                    <div class="pending-row"><span>Tenant</span><span>{{ $maintenanceRequest->tenant?->full_name ?? 'N/A' }}</span></div>
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Description</p>
                            <h3 class="dashboard-panel-title">Issue details</h3>
                        </div>
                    </div>
                    <p>{{ $maintenanceRequest->description }}</p>
                    @if ($maintenanceRequest->photos->isNotEmpty())
                        <div class="pending-row" style="margin-top: 0.75rem;"><span>Attachments</span><span>{{ $maintenanceRequest->photos->count() }} photo(s)</span></div>
                    @endif
                </article>

                @can('update', $maintenanceRequest)
                    <article class="form-card dashboard-panel">
                        <div class="dashboard-panel-head">
                            <div>
                                <p class="row-label">Staff controls</p>
                                <h3 class="dashboard-panel-title">Update lifecycle</h3>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('maintenance.update', $maintenanceRequest) }}">
                            @csrf
                            @method('PATCH')
                            <div class="two-up-grid">
                                <label class="field-group">
                                    <span class="field-label">Status</span>
                                    <select class="field-input" name="status" required>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected(old('status', $maintenanceRequest->status) === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Vendor</span>
                                    <input class="field-input" type="text" name="vendor_name" value="{{ old('vendor_name', $maintenanceRequest->vendor_name) }}">
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Repair cost</span>
                                    <input class="field-input" type="number" step="0.01" min="0" name="repair_cost" value="{{ old('repair_cost', $maintenanceRequest->repair_cost) }}">
                                </label>
                            </div>
                            <label class="field-group">
                                <span class="field-label">Internal notes</span>
                                <textarea class="field-input" rows="4" name="internal_notes">{{ old('internal_notes', $maintenanceRequest->internal_notes) }}</textarea>
                            </label>
                            <div class="btn-strip" style="margin-top: 0.75rem;">
                                <button class="btn btn-solid" type="submit">Update request</button>
                            </div>
                        </form>
                    </article>
                @endcan
            </div>
        </div>
    </div>
@endsection
