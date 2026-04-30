@extends('layouts.app', ['title' => 'Notifications | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Super Admin panel</p>
                        <h1 class="page-title">Notification center</h1>
                        <p class="page-description">Configure reminder triggers, run dispatch/retry actions, and monitor delivery outcomes.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.export.csv', $filterQuery) }}">Export CSV</a>
                        <form method="POST" action="{{ route('admin.notifications.dispatch-now') }}">
                            @csrf
                            <button class="btn btn-solid btn-sm" type="submit">Dispatch now</button>
                        </form>
                        <form method="POST" action="{{ route('admin.notifications.retry-failed') }}">
                            @csrf
                            <button class="btn btn-ghost btn-sm" type="submit">Retry failed</button>
                        </form>
                        <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Back to dashboard</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
                @endif

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Total records</p>
                        <h2 class="stat-value">{{ $summary['total'] }}</h2>
                        <p class="stat-meta"><span>latest delivery logs</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Sent</p>
                        <h2 class="stat-value">{{ $summary['sent'] }}</h2>
                        <p class="stat-meta"><span>successfully delivered</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Failed</p>
                        <h2 class="stat-value">{{ $summary['failed'] }}</h2>
                        <p class="stat-meta"><span>eligible for retry</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Pending</p>
                        <h2 class="stat-value">{{ $summary['pending'] }}</h2>
                        <p class="stat-meta"><span>queued status</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-side">
                        <article class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Event settings</p>
                                    <h3 class="dashboard-panel-title">Trigger configuration</h3>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('admin.notifications.settings.update') }}">
                                @csrf
                                @method('PUT')

                                @foreach ($settings as $setting)
                                    <div class="table-card" style="padding: 0.75rem; margin-bottom: 0.75rem;">
                                        <label class="field-group" style="margin-bottom: 0.5rem;">
                                            <span class="field-label">{{ str($setting['event_key'])->replace('_', ' ')->title() }}</span>
                                            <input type="hidden" name="events[{{ $setting['event_key'] }}][is_enabled]" value="0">
                                            <input type="checkbox" name="events[{{ $setting['event_key'] }}][is_enabled]" value="1" @checked($setting['is_enabled'])>
                                            <span class="muted-text">Enabled</span>
                                        </label>
                                        <label class="field-group">
                                            <span class="field-label">Lead / offset days</span>
                                            <input class="field-input" type="number" min="0" max="365" name="events[{{ $setting['event_key'] }}][lead_days]" value="{{ $setting['lead_days'] }}">
                                            <span class="security-meta">Default: {{ $setting['default_lead_days'] }} day(s)</span>
                                        </label>
                                    </div>
                                @endforeach

                                <button class="btn btn-solid" type="submit">Save trigger settings</button>
                            </form>
                        </article>
                    </div>

                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Delivery log</p>
                                    <h3 class="dashboard-panel-title">Recent notification attempts</h3>
                                </div>
                            </div>

                            <div class="btn-strip" style="margin-bottom: 0.75rem;">
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.index', ['status' => 'failed']) }}">Failed only</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]) }}">Today</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.index', ['date_from' => now()->subDays(6)->toDateString(), 'date_to' => now()->toDateString()]) }}">Last 7 days</a>
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.index', ['status' => 'failed', 'date_from' => now()->subDays(6)->toDateString(), 'date_to' => now()->toDateString()]) }}">Recent failures</a>
                            </div>

                            <form method="GET" action="{{ route('admin.notifications.index') }}" style="margin-bottom: 0.75rem;">
                                <div class="two-up-grid">
                                    <label class="field-group">
                                        <span class="field-label">Status</span>
                                        <select class="field-input" name="status">
                                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All statuses</option>
                                            <option value="sent" @selected(($filters['status'] ?? null) === 'sent')>Sent</option>
                                            <option value="failed" @selected(($filters['status'] ?? null) === 'failed')>Failed</option>
                                            <option value="pending" @selected(($filters['status'] ?? null) === 'pending')>Pending</option>
                                        </select>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Event key</span>
                                        <select class="field-input" name="event_key">
                                            <option value="">All events</option>
                                            @foreach ($eventKeys as $eventKey)
                                                <option value="{{ $eventKey }}" @selected(($filters['event_key'] ?? null) === $eventKey)>{{ str($eventKey)->replace('_', ' ')->title() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Date from</span>
                                        <input class="field-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Date to</span>
                                        <input class="field-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                                    </label>
                                    <label class="field-group">
                                        <span class="field-label">Recipient contains</span>
                                        <input class="field-input" type="text" name="recipient" value="{{ $filters['recipient'] ?? '' }}" placeholder="example.com">
                                    </label>
                                </div>
                                <div class="btn-strip" style="margin-top: 0.75rem;">
                                    <button class="btn btn-solid btn-sm" type="submit">Apply filters</button>
                                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.index', ['reset' => 1]) }}">Reset</a>
                                </div>
                            </form>

                            @if ($deliveries->isEmpty())
                                <p class="security-empty">No notification deliveries found yet. Run dispatch to generate logs.</p>
                            @else
                                <div class="data-table-card">
                                    <table class="data-table data-table-compact">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Recipient</th>
                                                <th>Status</th>
                                                <th>Channel</th>
                                                <th>Retries</th>
                                                <th>Sent</th>
                                                <th>Failed</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($deliveries as $delivery)
                                                <tr>
                                                    <td>{{ str($delivery->event_key)->replace('_', ' ')->title() }}</td>
                                                    <td>{{ $delivery->recipient_email ?: ($delivery->notifiable?->email ?: 'Unknown recipient') }}</td>
                                                    <td>{{ str($delivery->status)->title() }}</td>
                                                    <td>{{ str($delivery->channel)->title() }}</td>
                                                    <td>{{ $delivery->retry_count }}</td>
                                                    <td>{{ $delivery->sent_at?->format('M j, Y g:i A') ?: 'Not sent' }}</td>
                                                    <td>
                                                        {{ $delivery->failed_at?->format('M j, Y g:i A') ?: 'Not failed' }}
                                                        @if ($delivery->failure_reason)
                                                            <br><span class="muted-text">{{ $delivery->failure_reason }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($delivery->status === 'failed')
                                                            <form method="POST" action="{{ route('admin.notifications.retry-one', $delivery) }}">
                                                                @csrf
                                                                <button class="btn btn-ghost btn-sm" type="submit">Retry</button>
                                                            </form>
                                                        @else
                                                            <span class="muted-text">No action</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div style="margin-top: 0.75rem;">
                                    {{ $deliveries->links() }}
                                </div>
                            @endif
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
