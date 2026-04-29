@extends('layouts.app', ['title' => 'Property Detail | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Property detail</p>
                        <h1 class="page-title">{{ $property->title }}</h1>
                        <p class="page-description">{{ $property->street_address }}, {{ $property->city }}, {{ $property->state }} {{ $property->postal_code }}</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('properties.index') }}">Back to list</a>
                        @can('update', $property)
                            <a class="btn btn-solid" href="{{ route('properties.edit', $property) }}">Edit</a>
                        @endcan
                        @can('archive', $property)
                            <form method="POST" action="{{ route('properties.archive', $property) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-coral" type="submit">Archive</button>
                            </form>
                        @endcan
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Property ID</p>
                        <h2 class="stat-value">#{{ $property->id }}</h2>
                        <p class="stat-meta"><span>{{ str($property->type)->replace('_', ' ')->title() }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Lifecycle</p>
                        <h2 class="stat-value">{{ str($property->lifecycle_stage)->replace('_', ' ')->title() }}</h2>
                        <p class="stat-meta"><span>{{ $property->city }}, {{ $property->state }}</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Assigned managers</p>
                        <h2 class="stat-value">{{ $property->activeManagerAssignments->count() }}</h2>
                        <p class="stat-meta"><span>active access holders</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Media items</p>
                        <h2 class="stat-value">{{ $property->photos->count() }}</h2>
                        <p class="stat-meta"><span>{{ $property->activityLogs->count() }} activity records</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Overview</p>
                                    <h3 class="dashboard-panel-title">Property summary</h3>
                                </div>
                            </div>

                            <div class="pending-row"><span>Type</span><span>{{ str($property->type)->replace('_', ' ')->title() }}</span></div>
                            <div class="pending-row"><span>Lifecycle</span><span>{{ str($property->lifecycle_stage)->replace('_', ' ')->title() }}</span></div>
                            <div class="pending-row"><span>Area</span><span>{{ $property->area ? number_format((float) $property->area, 2).' '.$property->area_unit : 'Not set' }}</span></div>
                            <div class="pending-row"><span>Address</span><span>{{ $property->street_address }}, {{ $property->city }}, {{ $property->state }} {{ $property->postal_code }}, {{ $property->country }}</span></div>
                            <div class="pending-row"><span>Notes</span><span>{{ $property->description ?: 'No property notes added yet.' }}</span></div>
                        </article>

                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Manager access</p>
                                    <h3 class="dashboard-panel-title">Assignments</h3>
                                </div>
                            </div>

                            <div class="data-table-card">
                                <table class="data-table data-table-compact">
                                    <thead>
                                        <tr>
                                            <th scope="col">Manager</th>
                                            <th scope="col">Email</th>
                                            <th scope="col">Role</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($property->activeManagerAssignments as $assignment)
                                            <tr>
                                                <td><div class="data-table-primary">{{ $assignment->manager?->name ?? 'Unknown manager' }}</div></td>
                                                <td>{{ $assignment->manager?->email ?? 'No email' }}</td>
                                                <td><span class="badge badge-outline compact-badge">Manager</span></td>
                                                <td>
                                                    @can('assignManager', $property)
                                                        <form method="POST" action="{{ route('properties.assignments.destroy', [$property, $assignment]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-ghost btn-sm" type="submit">Revoke</button>
                                                        </form>
                                                    @else
                                                        <span class="muted-text">Access granted</span>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="data-table-empty">No manager assigned.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @can('assignManager', $property)
                                <form class="form-card property-inline-form" method="POST" action="{{ route('properties.assignments.store', $property) }}">
                                    @csrf
                                    <label class="field-group">
                                        <span class="field-label">Assign manager</span>
                                        <select class="field-input" name="manager_id" required>
                                            <option value="">Select a manager</option>
                                            @foreach ($managerOptions as $managerOption)
                                                <option value="{{ $managerOption->id }}">{{ $managerOption->name }} · {{ $managerOption->email }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button class="btn btn-solid" type="submit">Assign manager</button>
                                </form>
                            @endcan
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Media</p>
                                    <h3 class="dashboard-panel-title">Photos and cover</h3>
                                </div>
                            </div>

                            <div class="data-table-card">
                                <table class="data-table data-table-compact">
                                    <thead>
                                        <tr>
                                            <th scope="col">Photo</th>
                                            <th scope="col">Caption</th>
                                            <th scope="col">Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($property->photos as $photo)
                                            <tr>
                                                <td>
                                                    <div class="data-table-primary">Photo #{{ $photo->id }}</div>
                                                    <div class="data-table-secondary">Order {{ $photo->sort_order }}</div>
                                                </td>
                                                <td>{{ $photo->caption ?: 'No caption' }}</td>
                                                <td>
                                                    <span class="badge {{ $photo->is_cover ? 'badge-green' : 'badge-outline' }} compact-badge">{{ $photo->is_cover ? 'Cover' : 'Gallery' }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="data-table-empty">No photos uploaded yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </article>

                        <article class="feed-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Activity</p>
                                    <h3 class="dashboard-panel-title">Property timeline</h3>
                                </div>
                            </div>

                            @forelse ($property->activityLogs as $activity)
                                <div class="feed-item">
                                    <div class="feed-rail">
                                        <span class="feed-dot is-green"></span>
                                        @if (! $loop->last)
                                            <span class="feed-line"></span>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="feed-text">{{ $activity->label() }}</p>
                                        <p class="feed-meta">{{ $activity->actor?->name ?: 'System' }} · {{ $activity->occurred_at?->format('M j, Y g:i A') ?: 'Not recorded' }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="security-empty">No property activity logged yet.</p>
                            @endforelse
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
