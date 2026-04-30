@extends('layouts.app', ['title' => 'Notification Settings | PropMgr'])

@section('content')
    @php
        $eventCount = $settings->count();
        $activeCount = $settings->where('is_enabled', true)->count();
        $emailCount = $settings->where('email_enabled', true)->count();
        $whatsappCount = $settings->where('whatsapp_enabled', true)->count();
    @endphp

    <style>
        .notification-settings-page {
            --ns-accent: #0f766e;
            --ns-accent-soft: #ccfbf1;
            --ns-border: #d0d7e2;
            --ns-text-muted: #5f6b7f;
        }

        .notification-settings-page .settings-panel {
            background: linear-gradient(165deg, #f8fbff 0%, #ffffff 45%, #f6fffb 100%);
            border: 1px solid var(--ns-border);
            border-radius: 1rem;
            padding: 1rem;
        }

        .notification-settings-page .settings-summary {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            margin-bottom: 1rem;
        }

        .notification-settings-page .settings-pill {
            border: 1px solid var(--ns-border);
            background: #fff;
            border-radius: 0.8rem;
            padding: 0.6rem 0.8rem;
        }

        .notification-settings-page .settings-pill-label {
            margin: 0;
            font-size: 0.76rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--ns-text-muted);
            font-weight: 700;
        }

        .notification-settings-page .settings-pill-value {
            margin: 0.1rem 0 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #122033;
        }

        .notification-settings-page .event-list {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .notification-settings-page .event-card {
            border: 1px solid var(--ns-border);
            border-radius: 0.9rem;
            padding: 0.85rem;
            background: #fff;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
        }

        .notification-settings-page .event-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.8rem;
        }

        .notification-settings-page .event-title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #122033;
        }

        .notification-settings-page .event-meta {
            margin: 0.15rem 0 0;
            font-size: 0.8rem;
            color: var(--ns-text-muted);
        }

        .notification-settings-page .event-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: center;
        }

        .notification-settings-page .section-label {
            margin: 0 0 0.35rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ns-text-muted);
            font-weight: 700;
        }

        .notification-settings-page .event-status {
            min-width: 118px;
            margin: 0;
            border: 0;
            padding: 0;
            background: transparent;
        }

        .notification-settings-page .switch-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            border: 1px solid var(--ns-border);
            border-radius: 0.7rem;
            padding: 0.45rem 0.55rem;
            background: #fff;
        }

        .notification-settings-page .switch-row.is-disabled {
            opacity: 0.6;
        }

        .notification-settings-page .switch-row.is-disabled .switch-label {
            color: #7c8799;
        }

        .notification-settings-page .event-status.switch-row {
            justify-content: flex-end;
            border: 0;
            background: transparent;
        }

        .notification-settings-page .switch-label {
            margin: 0;
            font-size: 0.85rem;
            color: #1f2b3d;
            font-weight: 600;
        }

        .notification-settings-page .switch {
            position: relative;
            display: inline-block;
            width: 2.55rem;
            height: 1.45rem;
            flex: 0 0 auto;
        }

        .notification-settings-page .switch-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            margin: 0;
        }

        .notification-settings-page .switch-track {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            background: #c4ccd9;
            transition: background 0.2s ease;
        }

        .notification-settings-page .switch-track::before {
            content: '';
            position: absolute;
            height: 1.05rem;
            width: 1.05rem;
            left: 0.2rem;
            top: 0.2rem;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease;
        }

        .notification-settings-page .switch-input:checked + .switch-track {
            background: var(--ns-accent);
        }

        .notification-settings-page .switch-input:checked + .switch-track::before {
            transform: translateX(1.05rem);
        }

        .notification-settings-page .switch-input:focus-visible + .switch-track {
            outline: 2px solid #0ea5e9;
            outline-offset: 2px;
        }

        .notification-settings-page .offset-group {
            margin: 0;
            width: 100%;
        }

        .notification-settings-page .lead-days-row {
            min-height: 42px;
        }

        .notification-settings-page .offset-input {
            width: 52px;
            border: 0;
            border-radius: 0;
            padding: 0;
            font-size: 0.86rem;
            font-weight: 600;
            background: #fff;
            text-align: right;
        }

        .notification-settings-page .lead-days-control {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .notification-settings-page .offset-suffix {
            color: var(--ns-text-muted);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .notification-settings-page .offset-input:focus-visible {
            outline: 2px solid #0ea5e9;
            outline-offset: 2px;
            border-radius: 0.2rem;
        }

        .notification-settings-page .save-strip {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
        }

        @media (max-width: 920px) {
            .notification-settings-page .event-list {
                grid-template-columns: 1fr;
            }

            .notification-settings-page .event-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .notification-settings-page .event-grid {
                grid-template-columns: 1fr;
            }

            .notification-settings-page .event-card-head {
                align-items: center;
                flex-direction: row;
            }

            .notification-settings-page .event-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="ui-shell notification-settings-page">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Super Admin panel</p>
                        <h1 class="page-title">Notification settings</h1>
                        <p class="page-description">Use switches to activate events and channels, then tune reminder offsets by days.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Back to dashboard</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
                @endif

                <article class="dashboard-panel settings-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Event settings</p>
                            <h3 class="dashboard-panel-title">Trigger configuration</h3>
                        </div>
                    </div>

                    <div class="settings-summary">
                        <article class="settings-pill">
                            <p class="settings-pill-label">Total events</p>
                            <p class="settings-pill-value">{{ $eventCount }}</p>
                        </article>
                        <article class="settings-pill">
                            <p class="settings-pill-label">Active events</p>
                            <p class="settings-pill-value">{{ $activeCount }}</p>
                        </article>
                        <article class="settings-pill">
                            <p class="settings-pill-label">Email on</p>
                            <p class="settings-pill-value">{{ $emailCount }}</p>
                        </article>
                        <article class="settings-pill">
                            <p class="settings-pill-label">WhatsApp on</p>
                            <p class="settings-pill-value">{{ $whatsappCount }}</p>
                        </article>
                    </div>

                    <form method="POST" action="{{ route('admin.notifications.settings.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="event-list">
                            @foreach ($settings as $setting)
                                <article class="event-card" data-event-card>
                                    <div class="event-card-head">
                                        <div>
                                            <h4 class="event-title">{{ str($setting['event_key'])->replace('_', ' ')->title() }}</h4>
                                            <p class="event-meta">Default offset: {{ $setting['default_lead_days'] }} day(s)</p>
                                        </div>

                                        <label class="switch-row event-status">
                                            <span class="switch-label">Event enabled</span>
                                            <span class="switch">
                                                <input type="hidden" name="events[{{ $setting['event_key'] }}][is_enabled]" value="0">
                                                <input class="switch-input" data-event-master type="checkbox" name="events[{{ $setting['event_key'] }}][is_enabled]" value="1" @checked($setting['is_enabled'])>
                                                <span class="switch-track"></span>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="event-grid">
                                        <label class="switch-row" data-channel-row>
                                            <span class="switch-label">Email channel</span>
                                            <span class="switch">
                                                <input type="hidden" name="events[{{ $setting['event_key'] }}][email_enabled]" value="0">
                                                <input class="switch-input" data-event-channel type="checkbox" name="events[{{ $setting['event_key'] }}][email_enabled]" value="1" @checked($setting['email_enabled'])>
                                                <span class="switch-track"></span>
                                            </span>
                                        </label>

                                        <label class="switch-row" data-channel-row>
                                            <span class="switch-label">WhatsApp channel</span>
                                            <span class="switch">
                                                <input type="hidden" name="events[{{ $setting['event_key'] }}][whatsapp_enabled]" value="0">
                                                <input class="switch-input" data-event-channel type="checkbox" name="events[{{ $setting['event_key'] }}][whatsapp_enabled]" value="1" @checked($setting['whatsapp_enabled'])>
                                                <span class="switch-track"></span>
                                            </span>
                                        </label>

                                        <label class="offset-group" for="lead_days_{{ $setting['event_key'] }}">
                                            <span class="switch-row lead-days-row">
                                                <span class="switch-label">Lead days</span>
                                                <span class="lead-days-control">
                                                <input
                                                    id="lead_days_{{ $setting['event_key'] }}"
                                                    class="offset-input"
                                                    type="number"
                                                    min="0"
                                                    max="365"
                                                    name="events[{{ $setting['event_key'] }}][lead_days]"
                                                    value="{{ $setting['lead_days'] }}"
                                                >
                                                    <span class="offset-suffix">day(s)</span>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="save-strip">
                            <button class="btn btn-solid" type="submit">Save trigger settings</button>
                        </div>
                    </form>
                </article>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-event-card]').forEach((card) => {
                const master = card.querySelector('[data-event-master]');
                const channels = card.querySelectorAll('[data-event-channel]');
                const channelRows = card.querySelectorAll('[data-channel-row]');

                if (!master || channels.length === 0) {
                    return;
                }

                const syncChannels = () => {
                    const enabled = master.checked;

                    channels.forEach((channel) => {
                        if (!enabled) {
                            channel.checked = false;
                        }

                        channel.disabled = !enabled;
                    });

                    channelRows.forEach((row) => {
                        row.classList.toggle('is-disabled', !enabled);
                    });
                };

                syncChannels();
                master.addEventListener('change', syncChannels);
            });
        });
    </script>
@endsection
