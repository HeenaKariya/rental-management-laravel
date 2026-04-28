@extends('layouts.auth', [
    'title' => 'Two-Factor Challenge | PropMgr',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Two-factor challenge</h2>
        <p>Enter your authentication code or use a recovery code.</p>
    </div>

    <div class="tabs" role="tablist" aria-label="Two factor modes">
        <button class="tab is-active" type="button" data-ui-tab data-toggle-target="code-panel">Authenticator code</button>
        <button class="tab" type="button" data-ui-tab data-toggle-target="recovery-panel">Recovery code</button>
    </div>

    <form method="POST" action="{{ route('two-factor.login') }}" class="auth-form-grid auth-mode-panel is-visible" data-toggle-panel="code-panel">
        @csrf

        <label class="field-group">
            <span class="field-label">Authentication code</span>
            <input class="field-input @error('code') is-error @enderror" type="text" name="code" inputmode="numeric" autocomplete="one-time-code">
            @error('code')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <button class="btn btn-solid" type="submit">Verify code</button>
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