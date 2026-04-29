@extends('layouts.auth', [
    'title' => 'Invite User | PropMgr',
    'headline' => 'User creation is now invitation-driven and role-controlled.',
    'subhead' => 'Super Admins can issue a time-limited invite that preselects the recipient role and closes open self-registration.',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth Admin</p>
        <h2>Create invitation</h2>
        <p>Issue a role-scoped onboarding link for a manager, owner, or tenant.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
    @endif

    @if (session('invitation_url'))
        <div class="auth-alert auth-alert-info">
            Invitation link: <a class="auth-link" href="{{ session('invitation_url') }}">{{ session('invitation_url') }}</a>
        </div>
    @endif

    <form method="POST" action="{{ route('invitations.store') }}" class="auth-form-grid">
        @csrf

        <label class="field-group">
            <span class="field-label">Invitee email</span>
            <input class="field-input @error('email') is-error @enderror" type="email" name="email" value="{{ old('email') }}" required>
            @error('email')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <label class="field-group">
            <span class="field-label">Role</span>
            <select class="field-input @error('role') is-error @enderror" name="role" required>
                <option value="">Select a role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->slug }}" @selected(old('role') === $role->slug)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('role')<span class="field-hint is-error">{{ $message }}</span>@enderror
        </label>

        <button class="btn btn-solid" type="submit">Generate invitation</button>
    </form>
@endsection