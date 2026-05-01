@extends('layouts.app', ['title' => 'Properties | PropMgr'])

@section('content')
    @php
        $visiblePropertyCount = $properties->count();
        $activePropertyCount = $properties->where('lifecycle_stage', 'active')->count();
        $draftPropertyCount = $properties->where('lifecycle_stage', 'draft')->count();
        $assignedPropertyCount = $properties->filter(fn ($property) => $property->managers->isNotEmpty())->count();
        $featuredProperty = $properties->first();
    @endphp

    <div class="">
        <div class="py-2">
            <div class="d-flex flex-column gap-3">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Property workspace</p>
                        <h1 class="page-title">Manage the current portfolio</h1>
                        <p class="page-description">Review visibility, stage distribution, and manager coverage from one consistent property workspace.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                        @can('create', App\Models\Property::class)
                            <a class="btn btn-primary" href="{{ route('properties.create') }}">Add property</a>
                        @endcan
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status">
                        <span class="badge badge-green">{{ session('status') }}</span>
                    </div>
                @endif

                <section class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Visible properties</p>
                            <h2 class="stat-value">{{ $visiblePropertyCount }}</h2>
                            <p class="stat-meta"><span class="stat-pill positive">{{ $activePropertyCount }} active</span><span>{{ $draftPropertyCount }} draft</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Manager coverage</p>
                            <h2 class="stat-value">{{ $assignedPropertyCount }}</h2>
                            <p class="stat-meta"><span>assigned properties</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Property types</p>
                            <h2 class="stat-value">{{ count($propertyTypes) }}</h2>
                            <p class="stat-meta"><span>available classifications</span></p>
                        </article>
                    </div>
                    <div class="col">
                        <article class="card shadow-sm h-100 p-3">
                            <p class="stat-label">Lifecycle stages</p>
                            <h2 class="stat-value">{{ count($stageOptions) }}</h2>
                            <p class="stat-meta"><span>tracking statuses</span></p>
                        </article>
                    </div>
                </section>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Portfolio filters</p>
                            <h3 class="dashboard-panel-title">Narrow the property list</h3>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('properties.index') }}">
                        <div class="row">
                            <label class="col-lg-3">
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

                            <label class="col-lg-3">
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
                            @if ($user->hasRole('super_admin'))
                              <label class="col-lg-3">
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
                            <div class="col-3">
                              <div class="btn-strip" style="margin-top: 1.5rem;">
                                <button class="btn btn-primary" type="submit">Apply filters</button>
                                <a class="btn btn-outline-secondary" href="{{ route('properties.index') }}">Reset</a>
                              </div>
                            </div>
                            
                        </div>

                        

                        
                    </form>
                </article>

                <article class="card border-0 shadow-sm dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Property register</p>
                            <h3 class="dashboard-panel-title">Current portfolio</h3>
                        </div>
                        <span class="badge badge-outline">{{ $properties->count() }} total</span>
                    </div>

                    @if ($properties->isEmpty())
                        <p class="security-empty">No properties are visible for the current filters or assignment scope.</p>
                    @else
                        <div class="">
                            <table class="data-table js-data-table table w-100" data-page-size="10" data-empty-message="No properties matched the current filters.">
                                <thead>
                                    <tr>
                                        <th scope="col" data-sortable="false">Row</th>
                                        <th scope="col">ID</th>
                                        <th scope="col">Property</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Stage</th>
                                        <th scope="col">Managers</th>
                                        <th scope="col">Location</th>
                                        <th scope="col" data-sortable="false">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($properties as $property)
                                        <tr>
                                            <td data-row-number>{{ $loop->iteration }}</td>
                                            <td>#{{ $property->id }}</td>
                                            <td>
                                                <div class="data-table-primary">{{ $property->title }}</div>
                                                <div class="data-table-secondary">{{ $property->street_address }}</div>
                                            </td>
                                            <td>{{ str($property->type)->replace('_', ' ')->title() }}</td>
                                            <td>
                                                <span class="badge badge-violet compact-badge">{{ str($property->lifecycle_stage)->replace('_', ' ')->title() }}</span>
                                            </td>
                                            <td>{{ $property->managers->pluck('name')->implode(', ') ?: 'Unassigned' }}</td>
                                            <td>{{ $property->city }}, {{ $property->state }}</td>
                                            <td>
                                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('properties.show', $property) }}">Open</a>
                                            </td>
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
