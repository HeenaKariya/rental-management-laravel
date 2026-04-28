@extends('layouts.auth', [
    'title' => 'Forgot Password | PropMgr',
    'headline' => 'Password reset is part of the validated baseline auth surface.',
    'subhead' => 'Phase 1 will harden the recovery flow and tie it into the broader security model.',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Forgot password</h2>
        <p>Enter your email address and we will send you a reset link.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form-grid">
        @csrf

        <label class="field-group">
            <span class="field-label">Email</span>
            <input class="field-input @error('email') is-error @enderror" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            @error('email')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <div class="auth-actions">
            <button class="btn btn-solid" type="submit">Email reset link</button>
            <a class="auth-link" href="{{ route('login') }}">Back to login</a>
        </div>
    </form>
@endsection