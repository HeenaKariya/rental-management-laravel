@extends('layouts.app', ['title' => 'Create Tenant | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Create tenant</h1>
                        <p class="page-description">Start the tenant profile and KYC record that later lease and deposit flows will extend.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('tenants.index') }}">Back to tenants</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('tenants.store') }}" enctype="multipart/form-data">
                    @include('tenants._form', [
                        'cancelUrl' => route('tenants.index'),
                        'submitLabel' => 'Create tenant',
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection