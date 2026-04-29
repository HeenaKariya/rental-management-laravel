@extends('layouts.app', ['title' => 'Create Unit | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Create unit</h1>
                        <p class="page-description">Add the first inventory layer that later tenants and leases will depend on.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('units.index') }}">Back to units</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('units.store') }}">
                    @include('units._form', [
                        'cancelUrl' => route('units.index'),
                        'submitLabel' => 'Create unit',
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection