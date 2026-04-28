@extends('layouts.auth', [
    'title' => 'Register | PropMgr',
    'headline' => 'Registration is available in the baseline so the auth flow remains end-to-end valid.',
    'subhead' => 'Later phases can replace this with invitation-driven onboarding for tenants, owners, and managers.',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Create account</h2>
        <p>Baseline registration for the Fortify flow.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="auth-form-grid">
        @csrf

        <label class="field-group">
            <span class="field-label">Full name</span>
            <input class="field-input @error('name') is-error @enderror" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
            @error('name')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <label class="field-group">
            <span class="field-label">Email</span>
            <input class="field-input @error('email') is-error @enderror" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
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

        <div class="auth-actions">
            <button class="btn btn-solid" type="submit">Register</button>
            <a class="auth-link" href="{{ route('login') }}">Back to login</a>
        </div>
    </form>
@endsection