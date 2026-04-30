@extends('layouts.app', ['title' => 'Two-factor Controls | PropMgr'])

@section('content')
    @php
        $recoveryCodeCount = count($recoveryCodes);
    @endphp

    <div class="ui-shell px-0">
        <div class="ui-wrap px-0">
            <div class="dashboard-stack d-flex flex-column gap-3">
                <section class="page-header card-soft d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <p class="page-kicker">Security settings</p>
                        <h1 class="page-title">Manage two-factor authentication.</h1>
                        <p class="page-description">Confirm your second factor, rotate recovery codes, and manage access safety for your account.</p>
                    </div>

                    <div class="page-actions d-flex flex-wrap gap-2 align-items-center">
                        @if ($user->hasRole('super_admin'))
                            <a class="btn btn-violet btn-sm" href="{{ route('admin.security.two-factor.index') }}">Admin oversight</a>
                        @endif
                        <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Back to dashboard</a>
                    </div>
                </section>

                <section>
                    <article class="security-card dashboard-panel card border-0 p-3 p-lg-4">

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
                            <div class="row g-3 g-xl-4 align-items-start security-twofactor-layout">
                                <div class="col-12 col-xl-7 d-flex flex-column gap-3">
                                    <div class="dashboard-panel-head d-flex align-items-start justify-content-between gap-2 mb-0">
                                        <div>
                                            <p class="row-label">Two-factor controls</p>
                                            <h3 class="dashboard-panel-title mb-0">Enrollment and recovery</h3>
                                        </div>
                                    </div>
                                    <div class="badge-strip">
                                        @if ($twoFactorPendingConfirmation)
                                            <span class="badge badge-gold">Confirmation pending</span>
                                        @endif

                                        @if ($twoFactorEnabled)
                                            <span class="badge badge-green">{{ $usesDeliveredOtp ? 'Delivered OTP confirmed' : 'Authenticator app confirmed' }}</span>
                                        @endif

                                        <span class="badge badge-sky">{{ $recoveryCodeCount }} recovery codes ready</span>
                                    </div>

                                    @if ($twoFactorPendingConfirmation && $usesDeliveredOtp && $otpSetup)
                                        <p class="security-meta">
                                            {{ $otpSetup['channelLabel'] }} active until {{ $otpSetup['expiresAt']?->timezone('Asia/Kolkata')->format('M j, Y g:i A') }} IST.
                                            @if ($otpSetup['fallbackFrom'])
                                                Fallback from {{ $otpSetup['fallbackFrom'] === 'whatsapp' ? 'WhatsApp' : 'Email' }} was used.
                                            @endif
                                        </p>

                                        <form method="POST" action="{{ route('settings.security.two-factor.confirm') }}" class="security-inline-form row g-2 align-items-start mb-0">
                                            @csrf

                                            <label class="field-group col-12 col-lg-8 mb-0">
                                                <span class="field-label">One-time password</span>
                                                <input class="field-input @error('code') is-error @enderror" type="text" name="code" inputmode="numeric" autocomplete="one-time-code">
                                                @error('code')<span class="field-hint is-error">{{ $message }}</span>@enderror
                                            </label>

                                            <div class="btn-strip col-12 col-lg-auto">
                                                <button class="btn btn-solid" type="submit">Confirm delivered OTP</button>
                                            </div>
                                        </form>

                                        @php
                                            $availableChannels = $otpSetup['availableChannels'] ?? [];
                                            $emailAvailable = in_array('email', $availableChannels, true);
                                            $whatsappAvailable = in_array('whatsapp', $availableChannels, true);
                                        @endphp

                                        <div class="btn-strip d-flex flex-wrap gap-2 align-items-center pt-1">
                                            <form method="POST" action="{{ route('settings.security.two-factor.otp.resend') }}">
                                                @csrf
                                                <input type="hidden" name="channel" value="email">
                                                <button class="btn btn-ghost btn-sm" type="submit" @disabled(! $emailAvailable)>Resend via Email</button>
                                            </form>

                                            <form method="POST" action="{{ route('settings.security.two-factor.otp.resend') }}">
                                                @csrf
                                                <input type="hidden" name="channel" value="whatsapp">
                                                <button class="btn btn-ghost btn-sm" type="submit" @disabled(! $whatsappAvailable)>Resend via WhatsApp</button>
                                            </form>
                                        </div>

                                        @if (! $whatsappAvailable)
                                            <p class="security-log-meta mt-1">WhatsApp resend is unavailable because no phone number is configured on your account.</p>
                                        @endif
                                    @elseif ($twoFactorPendingConfirmation && $twoFactorQrCodeSvg)
                                        <div class="security-qr-frame">{!! $twoFactorQrCodeSvg !!}</div>

                                        <p class="security-meta">
                                            Scan this QR code with your authenticator app, then enter the first code below to complete enrollment.
                                        </p>

                                        <form method="POST" action="{{ route('settings.security.two-factor.confirm') }}" class="security-inline-form row g-2 align-items-end mb-0">
                                            @csrf

                                            <label class="field-group col-12 col-lg-8 mb-0">
                                                <span class="field-label">Authenticator code</span>
                                                <input class="field-input @error('code') is-error @enderror" type="text" name="code" inputmode="numeric" autocomplete="one-time-code">
                                                @error('code')<span class="field-hint is-error">{{ $message }}</span>@enderror
                                            </label>

                                            <div class="btn-strip col-12 col-lg-auto">
                                                <button class="btn btn-solid" type="submit">Confirm authenticator</button>
                                            </div>
                                        </form>
                                    @else
                                        <p class="security-meta mb-0">
                                            Two-factor authentication is confirmed. Keep your recovery codes secure and regenerate them if you think they may have been exposed.
                                        </p>
                                    @endif
                                </div>

                                <div class="col-12 col-xl-5">
                                    <div class="d-flex flex-column gap-3">
                                        <div>
                                            <p class="row-label">Recovery codes</p>

                                            @if ($user->recoveryCodeInventoryMessage())
                                                <div class="auth-alert {{ $user->hasLowRecoveryCodeInventory() || $user->remainingRecoveryCodesCount() === 0 ? 'auth-alert-warning' : 'auth-alert-info' }}">
                                                    {{ $user->recoveryCodeInventoryMessage() }}
                                                </div>
                                            @endif

                                            <div class="security-code-list">
                                                @foreach ($recoveryCodes as $recoveryCode)
                                                    <div class="security-code-item">{{ $recoveryCode }}</div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="security-actions d-flex flex-wrap gap-2">
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
                                    </div>
                                </div>
                            </div>
                        @endif
                    </article>
                </section>

                <section>
                    <article class="security-card dashboard-panel card border-0">
                        <div class="dashboard-panel-head d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <p class="row-label">Audit trail</p>
                                <h3 class="dashboard-panel-title">Recent authentication events</h3>
                            </div>
                        </div>

                        <div class="security-log-list">
                            @forelse ($auditLogs as $auditLog)
                                <article class="security-log-item">
                                    <div class="security-log-head">
                                        <span class="badge {{ $auditLog->badgeClass() }}">{{ $auditLog->label() }}</span>
                                        <span class="security-log-meta">{{ $auditLog->occurred_at?->timezone('Asia/Kolkata')->format('M j, Y g:i A') }} IST</span>
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
                                                {{ \Illuminate\Support\Str::limit($auditLog->user_agent, 72) }}
                                            @endif
                                        </p>
                                    @endif
                                </article>
                            @empty
                                <p class="security-empty">No security events have been recorded for this account yet.</p>
                            @endforelse
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </div>
@endsection
