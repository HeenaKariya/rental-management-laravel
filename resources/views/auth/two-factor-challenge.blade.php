@extends('layouts.auth', [
    'title' => 'Two-Factor Challenge | PropMgr',
    'headline' => 'A pre-session token is active until two-factor verification is completed.',
    'subhead' => 'Protected routes stay blocked until this challenge finishes successfully or the 15-minute token expires.',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Two-factor challenge</h2>
        <p>{{ $usesDeliveredOtp ? 'Enter the OTP we sent or use a recovery code.' : 'Enter your authentication code or use a recovery code.' }}</p>
    </div>

    <div class="tabs" role="tablist" aria-label="Two factor modes">
        <button class="tab is-active" type="button" data-ui-tab data-toggle-target="code-panel">{{ $usesDeliveredOtp ? 'One-time password' : 'Authenticator code' }}</button>
        <button class="tab" type="button" data-ui-tab data-toggle-target="recovery-panel">Recovery code</button>
    </div>

    @if ($preSession)
        <div class="auth-alert auth-alert-info">
            Pre-session token active until {{ $preSession->expires_at->format('M j, Y g:i A') }}.
        </div>
    @endif

    @if (session('otpStatus'))
        <div class="auth-alert auth-alert-success">{{ session('otpStatus') }}</div>
    @endif

    @if ($usesDeliveredOtp && $otpChallenge)
        <div class="auth-alert auth-alert-warning">
            {{ $otpChallenge['channelLabel'] }} active until {{ $otpChallenge['expiresAt']->format('M j, Y g:i A') }}.
            @if ($otpChallenge['fallbackFrom'])
                Fallback from {{ $otpChallenge['fallbackFrom'] === 'whatsapp' ? 'WhatsApp' : 'Email' }} was used.
            @endif
        </div>

        <div class="btn-strip">
            @foreach ($otpChallenge['availableChannels'] as $channel)
                <form method="POST" action="{{ route('two-factor.otp.resend') }}">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $channel }}">
                    <button class="btn btn-ghost btn-sm" type="submit">Resend via {{ $channel === 'whatsapp' ? 'WhatsApp' : 'Email' }}</button>
                </form>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.login') }}" class="auth-form-grid auth-mode-panel is-visible" data-toggle-panel="code-panel">
        @csrf

        <label class="field-group">
            <span class="field-label">{{ $usesDeliveredOtp ? 'One-time password' : 'Authentication code' }}</span>
            <input class="field-input @error('code') is-error @enderror" type="text" name="code" inputmode="numeric" autocomplete="one-time-code">
            @error('code')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <button class="btn btn-solid" type="submit">{{ $usesDeliveredOtp ? 'Verify OTP' : 'Verify code' }}</button>
    </form>

    <form method="POST" action="{{ route('two-factor.login') }}" class="auth-form-grid auth-mode-panel" data-toggle-panel="recovery-panel">
        @csrf

        <label class="field-group">
            <span class="field-label">Recovery code</span>
            <input class="field-input @error('recovery_code') is-error @enderror" type="text" name="recovery_code" autocomplete="one-time-code">
            @error('recovery_code')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <button class="btn btn-solid" type="submit">Use recovery code</button>
    </form>
@endsection