@extends('layouts.app', ['title' => 'Create Lease | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Create lease</h1>
                        <p class="page-description">Start the lease record and enforce the unit-level active lease boundary from the first write.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('leases.index') }}">Back to leases</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('leases.store') }}">
                    @include('leases._form', [
                        'cancelUrl' => route('leases.index'),
                        'submitLabel' => 'Create lease',
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection