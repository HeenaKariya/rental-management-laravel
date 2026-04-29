@extends('layouts.app', ['title' => 'Edit Tenant | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Edit tenant {{ $tenant->full_name }}</h1>
                        <p class="page-description">Update tenant status, KYC state, and supporting documents inside the scoped tenancy workspace.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('tenants.show', $tenant) }}">Back to tenant</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('tenants.update', $tenant) }}" enctype="multipart/form-data">
                    @method('PUT')
                    @include('tenants._form', [
                        'cancelUrl' => route('tenants.show', $tenant),
                        'submitLabel' => 'Save changes',
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection