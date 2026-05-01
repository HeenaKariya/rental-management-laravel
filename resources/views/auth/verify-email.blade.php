@extends('layouts.auth', [
    'title' => 'Verify Email | PropMgr',
])

@section('content')
    <div class="auth-panel-header">
        <p class="row-label">Auth</p>
        <h2>Verify email</h2>
        <p>Check your inbox for the verification link. If needed, request another email below.</p>
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="auth-alert auth-alert-success">A new verification link has been sent to your email address.</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="auth-form-grid">
        @csrf
        <button class="btn btn-primary" type="submit">Resend verification email</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn-outline-secondary" type="submit">Logout</button>
    </form>
@endsection