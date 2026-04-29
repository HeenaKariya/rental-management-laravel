@extends('layouts.app')

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
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

            <section class="stat-grid">
                <article class="stat-card">
                    <p class="stat-label">Type</p>
                    <h2 class="stat-value" style="font-size: 1.4rem;">{{ str($property->type)->replace('_', ' ')->title() }}</h2>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Lifecycle</p>
                    <h2 class="stat-value" style="font-size: 1.4rem;">{{ str($property->lifecycle_stage)->replace('_', ' ')->title() }}</h2>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Area</p>
                    <h2 class="stat-value" style="font-size: 1.4rem;">{{ $property->area ? number_format((float) $property->area, 2).' '.$property->area_unit : 'Not set' }}</h2>
                </article>
            </section>

            <section class="two-up-grid" style="margin-top: 1.5rem;">
                <article>
                    <p class="row-label">Managers</p>
                    <div class="pending-card">
                        @forelse ($property->activeManagerAssignments as $assignment)
                            <div class="pending-row">
                                <span>{{ $assignment->manager?->name }} · {{ $assignment->manager?->email }}</span>
                                @can('assignManager', $property)
                                    <form method="POST" action="{{ route('properties.assignments.destroy', [$property, $assignment]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-ghost btn-sm" type="submit">Revoke</button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <div class="pending-row"><span>No manager assigned</span></div>
                        @endforelse
                    </div>

                    @can('assignManager', $property)
                        <form class="form-card" style="margin-top: 1rem;" method="POST" action="{{ route('properties.assignments.store', $property) }}">
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

                <article>
                    <p class="row-label">Photos</p>
                    <div class="table-card">
                        @forelse ($property->photos as $photo)
                            <div class="table-row">
                                <div>
                                    <div class="tenant-name">Photo #{{ $photo->id }}</div>
                                    <div class="tenant-unit">{{ $photo->is_cover ? 'Cover image' : 'Gallery image' }}</div>
                                </div>
                                <span class="badge {{ $photo->is_cover ? 'badge-green' : 'badge-outline' }} compact-badge">
                                    {{ $photo->is_cover ? 'Cover' : 'Gallery' }}
                                </span>
                            </div>
                        @empty
                            <div class="table-row">
                                <div class="muted-text">No photos uploaded yet.</div>
                            </div>
                        @endforelse
                    </div>
                </article>
            </section>

            <section style="margin-top: 1.5rem;">
                <p class="row-label">Activity log</p>
                <div class="feed-card">
                    @forelse ($property->activityLogs as $activity)
                        <div class="feed-item">
                            <div class="feed-rail"><span class="feed-dot is-green"></span></div>
                            <div>
                                <p class="feed-text">{{ $activity->label() }}</p>
                                <p class="feed-meta">
                                    {{ $activity->occurred_at?->format('M j, Y g:i A') }}
                                    @if ($activity->actor)
                                        · by {{ $activity->actor->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="feed-item">
                            <div><p class="feed-text">No property activity logged yet.</p></div>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
