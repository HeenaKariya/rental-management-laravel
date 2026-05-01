@extends('layouts.auth', [
    'title' => 'Reset Password | PropMgr',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Reset password</h2>
        <p>Choose a new password for your account.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="auth-form-grid">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label class="field-group">
            <span class="field-label">Email</span>
            <input class="field-input @error('email') is-error @enderror" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username">
            @error('email')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <label class="field-group">
            <span class="field-label">Password</span>
            <input class="field-input @error('password') is-error @enderror" type="password" name="password" required autocomplete="new-password">
            @error('password')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <label class="field-group">
            <span class="field-label">Confirm password</span>
            <input class="field-input" type="password" name="password_confirmation" required autocomplete="new-password">
        </label>

        <button class="btn btn-primary" type="submit">Reset password</button>
    </form>
@endsection