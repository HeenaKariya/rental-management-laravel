@extends('layouts.auth', [
    'title' => 'Confirm Password | PropMgr',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Confirm password</h2>
        <p>Re-enter your password to continue.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form-grid">
        @csrf

        <label class="field-group">
            <span class="field-label">Password</span>
            <input class="field-input @error('password') is-error @enderror" type="password" name="password" required autocomplete="current-password">
            @error('password')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <button class="btn btn-primary" type="submit">Confirm password</button>
    </form>
@endsection