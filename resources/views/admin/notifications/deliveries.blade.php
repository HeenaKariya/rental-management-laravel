@extends('layouts.app', ['title' => 'Notification Deliveries | PropMgr'])

@section('content')
    @php
        $isWhatsappPage = ($currentChannel ?? 'email') === 'whatsapp';
        $deliveriesRoute = $isWhatsappPage ? 'admin.notifications.deliveries.whatsapp' : 'admin.notifications.deliveries.email';
        $channelLabel = $isWhatsappPage ? 'WhatsApp' : 'Email';
        $hasActiveFilters = !empty($filterQuery);
        $displayTimezone = 'Asia/Kolkata';
    @endphp

    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Super Admin panel</p>
                        <h1 class="page-title">{{ $isWhatsappPage ? 'WhatsApp delivery logs' : 'Email delivery logs' }}</h1>
                        <p class="page-description">Run dispatch/retry actions and monitor delivery outcomes.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.notifications.export.channel.csv', ['channel' => $currentChannel] + $filterQuery) }}">Export CSV</a>
                        <form method="POST" action="{{ route('admin.notifications.dispatch-now') }}">
                            @csrf
                            <input type="hidden" name="delivery_channel" value="{{ $currentChannel }}">
                            <button class="btn btn-solid btn-sm" type="submit">Dispatch now</button>
                        </form>
                        <form method="POST" action="{{ route('admin.notifications.retry-failed') }}">
                            @csrf
                            <input type="hidden" name="delivery_channel" value="{{ $currentChannel }}">
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

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Delivery log</p>
                            <h3 class="dashboard-panel-title">Recent notification attempts</h3>
                        </div>
                    </div>
                    <form method="GET" action="{{ route($deliveriesRoute) }}" class="mb-3">
                        <div class="row g-2">
                            <label class="col-12 col-md-2 field-group">
                                <span class="field-label">Status</span>
                                <select class="form-select" name="status">
                                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All statuses</option>
                                    <option value="sent" @selected(($filters['status'] ?? null) === 'sent')>Sent</option>
                                    <option value="failed" @selected(($filters['status'] ?? null) === 'failed')>Failed</option>
                                    <option value="pending" @selected(($filters['status'] ?? null) === 'pending')>Pending</option>
                                </select>
                            </label>
                            <label class="col-12 col-md-2 field-group">
                                <span class="field-label">Event key</span>
                                <select class="form-select" name="event_key">
                                    <option value="">All events</option>
                                    @foreach ($eventKeys as $eventKey)
                                        <option value="{{ $eventKey }}" @selected(($filters['event_key'] ?? null) === $eventKey)>{{ str($eventKey)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="col-12 col-md-2 field-group">
                                <span class="field-label">Recipient contains</span>
                                <input class="form-control" type="text" name="recipient" value="{{ $filters['recipient'] ?? '' }}" placeholder="example.com">
                            </label>
                            <label class="col-12 col-md-2 field-group">
                                <span class="field-label">Date from</span>
                                <input class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                            </label>
                            <label class="col-12 col-md-2 field-group">
                                <span class="field-label">Date to</span>
                                <input class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                            </label>
                            <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                                <button class="btn btn-solid btn-sm" type="submit">Apply filters</button>
                                <a class="btn btn-ghost btn-sm" href="{{ route($deliveriesRoute, ['reset' => 1]) }}">Reset</a>
                            </div>
                        </div>
                    </form>

                    @if ($deliveries->isEmpty())
                        <p class="security-empty">
                            No {{ $channelLabel }} delivery logs found{{ $hasActiveFilters ? ' for the current filters' : '' }}.
                            @if ($hasActiveFilters)
                                Try <a href="{{ route($deliveriesRoute, ['reset' => 1]) }}">Reset</a> to clear filters.
                            @else
                                Run dispatch to generate logs.
                            @endif
                        </p>
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
                                            <td>{{ $delivery->sent_at ? $delivery->sent_at->copy()->timezone($displayTimezone)->format('M j, Y g:i A').' IST' : 'Not sent' }}</td>
                                            <td>
                                                {{ $delivery->failed_at ? $delivery->failed_at->copy()->timezone($displayTimezone)->format('M j, Y g:i A').' IST' : 'Not failed' }}
                                                @if ($delivery->failure_reason)
                                                    <br><span class="muted-text">{{ $delivery->failure_reason }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($delivery->status === 'failed')
                                                    <form method="POST" action="{{ route('admin.notifications.retry-one', $delivery) }}">
                                                        @csrf
                                                        <input type="hidden" name="delivery_channel" value="{{ $currentChannel }}">
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
        </div>
    </div>
@endsection
