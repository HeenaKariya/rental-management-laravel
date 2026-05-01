@extends('layouts.auth', [
    'title' => 'Register | PropMgr',
    'headline' => 'Registration is invitation-only and role-aware.',
    'subhead' => 'Each onboarding link is tied to one email address and one target role.',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Create account</h2>
        <p>Complete the invitation to activate your access.</p>
    </div>

    @if (! $invitation)
        <div class="auth-alert auth-alert-warning">A valid invitation link is required to create an account.</div>
        <p class="auth-footer-copy">Ask a Super Admin to issue a new invitation.</p>
    @else
        <div class="auth-alert auth-alert-info">
            Invited as <strong>{{ $invitation->role->name }}</strong> for <strong>{{ $invitation->email }}</strong>.
        </div>

        <form method="POST" action="{{ route('register') }}" class="auth-form-grid">
            @csrf
            <input type="hidden" name="invitation_token" value="{{ old('invitation_token', $invitationToken) }}">

            <label class="field-group">
                <span class="field-label">Full name</span>
                <input class="field-input @error('name') is-error @enderror" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
                @error('name')<span class="field-hint is-error">{{ $message }}</span>@enderror
            </label>

            <label class="field-group">
                <span class="field-label">Email</span>
                <input class="field-input @error('email') is-error @enderror" type="email" name="email" value="{{ old('email', $invitation->email) }}" required autocomplete="username">
                @error('email')<span class="field-hint is-error">{{ $message }}</span>@enderror
            </label>

            <label class="field-group">
                <span class="field-label">Password</span>
                <input class="field-input @error('password') is-error @enderror" type="password" name="password" required autocomplete="new-password">
                @error('password')<span class="field-hint is-error">{{ $message }}</span>@enderror
            </label>

            @error('invitation_token')<span class="field-hint is-error">{{ $message }}</span>@enderror

            <label class="field-group">
                <span class="field-label">Confirm password</span>
                <input class="field-input" type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <div class="auth-actions">
                <button class="btn btn-primary" type="submit">Register</button>
                <a class="auth-link" href="{{ route('login') }}">Back to login</a>
            </div>
        </form>
    @endif
@endsection