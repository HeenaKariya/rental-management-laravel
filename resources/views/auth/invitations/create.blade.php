@extends('layouts.app', ['title' => 'Invitations | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Invitation admin</p>
                        <h1 class="page-title">Create invitation</h1>
                        <p class="page-description">Issue a role-scoped onboarding link for a manager, owner, or tenant from the same full workspace layout as the dashboard.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Back to dashboard</a>
                    </div>
                </section>

                <section class="stat-grid dashboard-stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Available roles</p>
                        <h2 class="stat-value">{{ $roles->count() }}</h2>
                        <p class="stat-meta"><span>role-scoped invites</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Invite flow</p>
                        <h2 class="stat-value">Scoped</h2>
                        <p class="stat-meta"><span>time-limited onboarding</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Registration model</p>
                        <h2 class="stat-value">Closed</h2>
                        <p class="stat-meta"><span>invitation-only access</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Latest output</p>
                        <h2 class="stat-value">{{ session('invitation_url') ? 'Ready' : 'Idle' }}</h2>
                        <p class="stat-meta"><span>{{ session('invitation_url') ? 'link generated' : 'awaiting submission' }}</span></p>
                    </article>
                </section>

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="form-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Invitation composer</p>
                                    <h3 class="dashboard-panel-title">Generate a role-scoped invite</h3>
                                </div>
                            </div>

                            @if (session('status'))
                                <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
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
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Latest invitation</p>
                                    <h3 class="dashboard-panel-title">Generated link</h3>
                                </div>
                            </div>

                            @if (session('invitation_url'))
                                <div class="auth-alert auth-alert-info">
                                    Invitation link: <a class="auth-link" href="{{ session('invitation_url') }}">{{ session('invitation_url') }}</a>
                                </div>
                            @else
                                <p class="security-empty">Generate an invitation to display the onboarding link here.</p>
                            @endif
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection