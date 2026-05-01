@extends('layouts.auth', [
    'title' => 'Login | PropMgr',
    'headline' => 'Secure sign in with rate limiting and two-factor support.',
    'subhead' => 'This Phase 0 login screen is intentionally minimal and reusable. It gives Fortify valid view endpoints without locking the project into a starter-kit layout.',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Sign in</h2>
        <p>Use your email and password to continue into the dashboard.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form-grid">
        @csrf

        <label class="field-group">
            <span class="field-label">Email</span>
            <input class="field-input @error('email') is-error @enderror" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <label class="field-group">
            <span class="field-label">Password</span>
            <input class="field-input @error('password') is-error @enderror" type="password" name="password" required autocomplete="current-password">
            @error('password')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <label class="auth-inline-check">
            <input type="checkbox" name="remember">
            <span>Remember this device</span>
        </label>

        <div class="auth-actions">
            <button class="btn btn-primary" type="submit">Login</button>
            <a class="auth-link" href="{{ route('password.request') }}">Forgot password?</a>
        </div>
    </form>
    <p class="auth-footer-copy">New accounts are created by invitation from a Super Admin.</p>
@endsection