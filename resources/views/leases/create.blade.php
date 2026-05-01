@extends('layouts.app', ['title' => 'Create Lease | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Create lease</h1>
                        <p class="page-description">Start the lease record and enforce the unit-level active lease boundary from the first write.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('leases.index') }}">Back to leases</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('leases.store') }}">
                    @include('leases._form', [
                        'cancelUrl' => route('leases.index'),
                        'submitLabel' => 'Create lease',
                        'vacancyGapContext' => $vacancyGapContext,
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection