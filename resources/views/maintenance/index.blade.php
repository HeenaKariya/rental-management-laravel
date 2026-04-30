@extends('layouts.app', ['title' => 'Maintenance Requests | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Operations</p>
                        <h1 class="page-title">Maintenance requests</h1>
                        <p class="page-description">Track issue lifecycle across units with role-based visibility.</p>
                    </div>
                    <div class="page-actions">
                        <a class="btn btn-solid" href="{{ route('maintenance.create') }}">New request</a>
                        <a class="btn btn-ghost" href="{{ route('dashboard') }}">Dashboard</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status"><span class="badge badge-green">{{ session('status') }}</span></div>
                @endif

                <article class="form-card dashboard-panel">
                    <form method="GET" action="{{ route('maintenance.index') }}">
                        <div class="two-up-grid">
                            <label class="field-group">
                                <span class="field-label">Status</span>
                                <select class="field-input" name="status">
                                    <option value="">All statuses</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span class="field-label">Priority</span>
                                <select class="field-input" name="priority">
                                    <option value="">All priorities</option>
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ str($priority)->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <div class="btn-strip" style="margin-top: 0.75rem;">
                            <button class="btn btn-solid btn-sm" type="submit">Apply</button>
                            <a class="btn btn-ghost btn-sm" href="{{ route('maintenance.index') }}">Reset</a>
                        </div>
                    </form>
                </article>

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Request list</p>
                            <h3 class="dashboard-panel-title">Current issues</h3>
                        </div>
                    </div>

                    @if ($requests->isEmpty())
                        <p class="security-empty">No maintenance requests found for the selected filters.</p>
                    @else
                        <div class="data-table-card">
                            <table class="data-table data-table-compact">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Property / Unit</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($requests as $maintenanceRequest)
                                        <tr>
                                            <td>
                                                <div class="data-table-primary">{{ $maintenanceRequest->title }}</div>
                                                <div class="data-table-secondary">{{ str($maintenanceRequest->category)->replace('_', ' ')->title() }}</div>
                                            </td>
                                            <td>{{ $maintenanceRequest->unit?->property?->title }} · {{ $maintenanceRequest->unit?->unit_number }}</td>
                                            <td>{{ str($maintenanceRequest->priority)->title() }}</td>
                                            <td>{{ str($maintenanceRequest->status)->replace('_', ' ')->title() }}</td>
                                            <td>{{ $maintenanceRequest->created_at?->format('M j, Y g:i A') }}</td>
                                            <td><a class="btn btn-ghost btn-sm" href="{{ route('maintenance.show', $maintenanceRequest) }}">Open</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </article>
            </div>
        </div>
    </div>
@endsection
