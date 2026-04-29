@extends('layouts.app', ['title' => 'Security Settings | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <header class="identity-card">
                <div class="identity-brand">
                    <div class="logo-mark">P</div>
                    <div>
                        <p class="logo-text">PropMgr</p>
                        <p class="eyebrow-text">Authentication and recovery controls</p>
                    </div>
                </div>

                <div class="identity-actions">
                    <span class="badge badge-ink">{{ $twoFactorEnabled ? '2FA active' : '2FA not confirmed' }}</span>
                    @if ($user->hasRole('super_admin'))
                        <a class="btn btn-violet btn-sm" href="{{ route('admin.security.two-factor.index') }}">Admin oversight</a>
                    @endif
                    <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Back to dashboard</a>
                </div>
            </header>

            <section class="security-grid">
                <article class="security-card">
                    <div>
                        <p class="row-label">Security settings</p>
                        <h1 class="security-title">Manage two-factor authentication.</h1>
                        <p class="security-copy">
                            Confirm your second factor, rotate recovery codes, and review the recent audit trail
                            for your account.
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
                    @endif

                    @if (! $twoFactorEnabled && ! $twoFactorPendingConfirmation)
                        <div class="badge-strip">
                            <span class="badge badge-outline">{{ $usesDeliveredOtp ? 'Delivered OTP required' : 'Authenticator app required' }}</span>
                            <span class="badge badge-outline">Recovery codes unavailable</span>
                        </div>

                        <p class="security-meta">
                            {{ $usesDeliveredOtp
                                ? 'Start setup to deliver an email or WhatsApp OTP and generate recovery codes. Password confirmation is required before any security change is applied.'
                                : 'Start setup to generate a shared secret, QR code, and recovery codes. Password confirmation is required before any security change is applied.' }}
                        </p>

                        <form method="POST" action="{{ route('settings.security.two-factor.enable') }}">
                            @csrf

                            <button class="btn btn-solid" type="submit">Start 2FA setup</button>
                        </form>
                    @else
                        <div class="badge-strip">
                            @if ($twoFactorPendingConfirmation)
                                <span class="badge badge-gold">Confirmation pending</span>
                            @endif

                            @if ($twoFactorEnabled)
                                <span class="badge badge-green">{{ $usesDeliveredOtp ? 'Delivered OTP confirmed' : 'Authenticator app confirmed' }}</span>
                            @endif

                            <span class="badge badge-sky">{{ count($recoveryCodes) }} recovery codes ready</span>
                        </div>

                        @if ($twoFactorPendingConfirmation && $usesDeliveredOtp && $otpSetup)
                            <p class="security-meta">
                                {{ $otpSetup['channelLabel'] }} active until {{ $otpSetup['expiresAt']->format('M j, Y g:i A') }}.
                                @if ($otpSetup['fallbackFrom'])
                                    Fallback from {{ $otpSetup['fallbackFrom'] === 'whatsapp' ? 'WhatsApp' : 'Email' }} was used.
                                @endif
                            </p>

                            <form method="POST" action="{{ route('settings.security.two-factor.confirm') }}" class="security-inline-form">
                                @csrf

                                <label class="field-group">
                                    <span class="field-label">One-time password</span>
                                    <input class="field-input @error('code') is-error @enderror" type="text" name="code" inputmode="numeric" autocomplete="one-time-code">
                                    @error('code')<span class="field-hint is-error">{{ $message }}</span>@enderror
                                </label>

                                <div class="btn-strip">
                                    <button class="btn btn-solid" type="submit">Confirm delivered OTP</button>
                                    @foreach ($otpSetup['availableChannels'] as $channel)
                                        <form method="POST" action="{{ route('settings.security.two-factor.otp.resend') }}">
                                            @csrf
                                            <input type="hidden" name="channel" value="{{ $channel }}">
                                            <button class="btn btn-ghost btn-sm" type="submit">Resend via {{ $channel === 'whatsapp' ? 'WhatsApp' : 'Email' }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            </form>
                        @elseif ($twoFactorPendingConfirmation && $twoFactorQrCodeSvg)
                            <div class="security-qr-frame">{!! $twoFactorQrCodeSvg !!}</div>

                            <p class="security-meta">
                                Scan this QR code with your authenticator app, then enter the first code below to
                                complete enrollment.
                            </p>

                            <form method="POST" action="{{ route('settings.security.two-factor.confirm') }}" class="security-inline-form">
                                @csrf

                                <label class="field-group">
                                    <span class="field-label">Authenticator code</span>
                                    <input class="field-input @error('code') is-error @enderror" type="text" name="code" inputmode="numeric" autocomplete="one-time-code">
                                    @error('code')<span class="field-hint is-error">{{ $message }}</span>@enderror
                                </label>

                                <div class="btn-strip">
                                    <button class="btn btn-solid" type="submit">Confirm authenticator</button>
                                </div>
                            </form>
                        @endif

                        <div>
                            <p class="row-label">Recovery codes</p>

                            <div class="security-code-list">
                                @foreach ($recoveryCodes as $recoveryCode)
                                    <div class="security-code-item">{{ $recoveryCode }}</div>
                                @endforeach
                            </div>
                        </div>

                        <div class="security-actions">
                            <form method="POST" action="{{ route('settings.security.two-factor.recovery-codes') }}">
                                @csrf

                                <button class="btn btn-lime" type="submit">Regenerate recovery codes</button>
                            </form>

                            <form method="POST" action="{{ route('settings.security.two-factor.disable') }}">
                                @csrf
                                @method('DELETE')

                                <button class="btn btn-ghost" type="submit">Disable 2FA</button>
                            </form>
                        </div>
                    @endif
                </article>

                <aside class="security-card">
                    <div>
                        <p class="row-label">Audit trail</p>
                        <h2 class="hero-text" style="margin-top: 0; max-width: none;">Recent authentication events</h2>
                    </div>

                    <div class="security-log-list">
                        @forelse ($auditLogs as $auditLog)
                            <article class="security-log-item">
                                <div class="security-log-head">
                                    <span class="badge {{ $auditLog->badgeClass() }}">{{ $auditLog->label() }}</span>
                                    <span class="security-log-meta">{{ $auditLog->occurred_at?->format('M j, Y g:i A') }}</span>
                                </div>

                                @if ($auditLog->summary())
                                    <p class="security-log-meta">{{ $auditLog->summary() }}</p>
                                @endif

                                @if ($auditLog->ip_address || $auditLog->user_agent)
                                    <p class="security-log-meta">
                                        @if ($auditLog->ip_address)
                                            {{ $auditLog->ip_address }}
                                        @endif
                                        @if ($auditLog->ip_address && $auditLog->user_agent)
                                            ·
                                        @endif
                                        @if ($auditLog->user_agent)
                                            {{ \Illuminate\Support\Str::limit($auditLog->user_agent, 48) }}
                                        @endif
                                    </p>
                                @endif
                            </article>
                        @empty
                            <p class="security-empty">No security events have been recorded for this account yet.</p>
                        @endforelse
                    </div>
                </aside>
            </section>
        </div>
    </div>
@endsection