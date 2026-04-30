@extends('layouts.app', ['title' => 'Invitations | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Invitation admin</p>
                        <h1 class="page-title">Create invitation</h1>
                        <p class="page-description">Send a role-based onboarding invite by email and optional WhatsApp.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Back to dashboard</a>
                    </div>
                </section>

                <section class="row g-3">
                    <div class="col-12 col-xl-8">
                        <article class="table-card dashboard-panel p-3 p-md-4">
                            <div class="dashboard-panel-head mb-3">
                                <div>
                                    <p class="row-label">Invitation composer</p>
                                    <h3 class="dashboard-panel-title">Generate role-scoped invite</h3>
                                </div>
                            </div>

                            @if (session('status'))
                                <div class="auth-alert auth-alert-success">{{ session('status') }}</div>
                            @endif

                            @if ($whatsappOnlyMode)
                                <div class="auth-alert auth-alert-info">Email channel is disabled for invitation delivery. WhatsApp phone is required; email becomes optional for send channel.</div>
                            @endif

                            <form method="POST" action="{{ route('invitations.store') }}" class="row g-3">
                                @csrf

                                <div class="col-12">
                                    <label class="field-label" for="invite_email">Invitee email{{ $whatsappOnlyMode ? ' (optional)' : '' }}</label>
                                    <input id="invite_email" class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" placeholder="name@example.com" @if(! $whatsappOnlyMode) required @endif>
                                    <div class="form-text">Used for account registration identity. Required unless invitation delivery is configured as WhatsApp-only.</div>
                                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="field-label" for="invite_phone">WhatsApp phone{{ $whatsappOnlyMode ? '' : ' (optional)' }}</label>
                                    <input id="invite_phone" class="form-control @error('phone') is-invalid @enderror" type="text" name="phone" value="{{ old('phone') }}" placeholder="+919999999999" @if($whatsappOnlyMode) required @endif>
                                    @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="field-label" for="invite_role">Role</label>
                                    <select id="invite_role" class="form-select @error('role') is-invalid @enderror" name="role" required>
                                        <option value="">Select a role</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->slug }}" @selected(old('role') === $role->slug)>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 d-flex gap-2">
                                    <button class="btn btn-solid" type="submit">Generate invitation</button>
                                    <a class="btn btn-ghost" href="{{ route('dashboard') }}">Cancel</a>
                                </div>
                            </form>
                        </article>
                    </div>

                    <div class="col-12 col-xl-4">
                        <article class="security-card dashboard-panel h-100">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Latest invitation</p>
                                    <h3 class="dashboard-panel-title">Generated link</h3>
                                </div>
                            </div>

                            @if (session('invitation_url'))
                                <div class="auth-alert auth-alert-info">
                                    <p class="mb-2"><strong>Invitation URL</strong></p>
                                    <a class="auth-link" href="{{ session('invitation_url') }}">{{ session('invitation_url') }}</a>
                                </div>
                            @else
                                <p class="security-empty">Generate an invitation and the onboarding URL will appear here.</p>
                            @endif
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection