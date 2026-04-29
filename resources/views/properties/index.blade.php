@extends('layouts.app')

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <section class="page-header card-soft">
                <div>
                    <p class="page-kicker">Phase 2</p>
                    <h1 class="page-title">Properties</h1>
                    <p class="page-description">Manager-scoped property visibility, lifecycle status, and assignment coverage start here.</p>
                </div>

                <div class="page-actions">
                    <a class="btn btn-ghost" href="{{ route('dashboard') }}">Dashboard</a>
                    @can('create', App\Models\Property::class)
                        <a class="btn btn-solid" href="{{ route('properties.create') }}">Add property</a>
                    @endcan
                </div>
            </section>

            @if (session('status'))
                <div class="page-status">
                    <span class="badge badge-green">{{ session('status') }}</span>
                </div>
            @endif

            <form class="form-card" method="GET" action="{{ route('properties.index') }}">
                <div class="two-up-grid">
                    <label class="field-group">
                        <span class="field-label">Type</span>
                        <select class="field-input" name="type">
                            <option value="">All types</option>
                            @foreach ($propertyTypes as $propertyType)
                                <option value="{{ $propertyType }}" @selected(($filters['type'] ?? null) === $propertyType)>
                                    {{ str($propertyType)->replace('_', ' ')->title() }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field-group">
                        <span class="field-label">Lifecycle stage</span>
                        <select class="field-input" name="lifecycle_stage">
                            <option value="">All stages</option>
                            @foreach ($stageOptions as $stageOption)
                                <option value="{{ $stageOption }}" @selected(($filters['lifecycle_stage'] ?? null) === $stageOption)>
                                    {{ str($stageOption)->replace('_', ' ')->title() }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @if ($user->hasRole('super_admin'))
                    <label class="field-group">
                        <span class="field-label">Assigned manager</span>
                        <select class="field-input" name="assigned_manager_id">
                            <option value="">All managers</option>
                            @foreach ($managerOptions as $managerOption)
                                <option value="{{ $managerOption->id }}" @selected((int) ($filters['assigned_manager_id'] ?? 0) === $managerOption->id)>
                                    {{ $managerOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <div class="btn-strip">
                    <button class="btn btn-solid" type="submit">Apply filters</button>
                    <a class="btn btn-ghost" href="{{ route('properties.index') }}">Reset</a>
                </div>
            </form>

            @if ($properties->isEmpty())
                <div class="card-soft page-empty-state" style="margin-top: 1.5rem;">
                    <p class="page-kicker">No results</p>
                    <p class="page-description">No properties are visible for the current filters or assignment scope.</p>
                </div>
            @else
                <div class="table-card" style="margin-top: 1.5rem;">
                    <div class="table-head">
                        <span>Property</span>
                        <span>Type</span>
                        <span>Stage</span>
                        <span>Managers</span>
                    </div>

                    @foreach ($properties as $property)
                        <a class="table-row" href="{{ route('properties.show', $property) }}" style="text-decoration: none; color: inherit;">
                            <div>
                                <div class="tenant-name">{{ $property->title }}</div>
                                <div class="tenant-unit">{{ $property->city }}, {{ $property->state }}</div>
                            </div>
                            <div class="muted-text">{{ str($property->type)->replace('_', ' ')->title() }}</div>
                            <span class="badge badge-violet compact-badge">{{ str($property->lifecycle_stage)->replace('_', ' ')->title() }}</span>
                            <div class="muted-text">{{ $property->managers->pluck('name')->implode(', ') ?: 'Unassigned' }}</div>
                        </a>
                    @endforeach
                </div>

                <div style="margin-top: 1rem;">
                    {{ $properties->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
