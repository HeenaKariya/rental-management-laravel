@extends('layouts.app', ['title' => 'Edit Lease | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Edit lease {{ $lease->lease_number }}</h1>
                        <p class="page-description">Adjust dates, status, and commercial terms while preserving the one-active-lease boundary.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('leases.show', $lease) }}">Back to lease</a>
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