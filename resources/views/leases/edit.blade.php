@extends('layouts.app', ['title' => 'Edit Lease | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Edit lease {{ $lease->lease_number }}</h1>
                        <p class="page-description">Adjust dates, status, and commercial terms while preserving the one-active-lease boundary.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('leases.show', $lease) }}">Back to lease</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('leases.update', $lease) }}">
                    @method('PUT')
                    @include('leases._form', [
                        'cancelUrl' => route('leases.show', $lease),
                        'submitLabel' => 'Save changes',
                        'vacancyGapContext' => $vacancyGapContext,
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection