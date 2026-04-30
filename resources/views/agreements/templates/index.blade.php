@extends('layouts.app', ['title' => 'Agreement Templates | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 6 agreement</p>
                        <h1 class="page-title">Agreement templates</h1>
                        <p class="page-description">Create, revise, and activate template bodies with placeholder tokens for lease-level generation.</p>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status"><span class="badge badge-green">{{ session('status') }}</span></div>
                @endif

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Template list</p>
                                    <h3 class="dashboard-panel-title">Existing templates</h3>
                                </div>
                            </div>

                            @if ($templates->isEmpty())
                                <p class="security-empty">No agreement templates yet.</p>
                            @else
                                @foreach ($templates as $template)
                                    <form class="form-card" method="POST" action="{{ route('agreements.templates.update', $template) }}" style="margin-bottom: 1rem;">
                                        @csrf
                                        @method('PUT')
                                        <div class="two-up-grid">
                                            <label class="field-group">
                                                <span class="field-label">Template name</span>
                                                <input class="field-input" name="name" value="{{ old('name', $template->name) }}" required>
                                            </label>
                                            <label class="field-group">
                                                <span class="field-label">Status</span>
                                                <select class="field-input" name="status">
                                                    @foreach ($statusOptions as $statusOption)
                                                        <option value="{{ $statusOption }}" @selected($template->status === $statusOption)>{{ str($statusOption)->title() }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </div>
                                        <label class="field-group">
                                            <span class="field-label">Body HTML</span>
                                            <textarea class="field-input" name="body_html" rows="6" required>{{ old('body_html', $template->body_html) }}</textarea>
                                        </label>
                                        <div class="btn-strip">
                                            <button class="btn btn-solid btn-sm" type="submit">Update</button>
                                            @can('delete', $template)
                                                <button class="btn btn-coral btn-sm" type="submit" formaction="{{ route('agreements.templates.destroy', $template) }}" formmethod="POST" onclick="event.preventDefault(); if(confirm('Delete this template?')) { this.closest('form').append(Object.assign(document.createElement('input'), {type:'hidden', name:'_method', value:'DELETE'})); this.closest('form').submit(); }">Delete</button>
                                            @endcan
                                        </div>
                                    </form>
                                @endforeach
                            @endif
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Create</p>
                                    <h3 class="dashboard-panel-title">New template</h3>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('agreements.templates.store') }}">
                                @csrf
                                <label class="field-group">
                                    <span class="field-label">Template name</span>
                                    <input class="field-input" name="name" required>
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Status</span>
                                    <select class="field-input" name="status">
                                        @foreach ($statusOptions as $statusOption)
                                            <option value="{{ $statusOption }}">{{ str($statusOption)->title() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Body HTML</span>
                                    <textarea class="field-input" name="body_html" rows="8" required><p>Tenant {{tenant_name}} agrees to lease {{property_name}}/{{unit_number}} from {{lease_start_date}} to {{lease_end_date}} for {{monthly_rent}}.</p></textarea>
                                </label>
                                <button class="btn btn-solid" type="submit">Create template</button>
                            </form>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection