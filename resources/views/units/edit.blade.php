@extends('layouts.app', ['title' => 'Edit Unit | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 3 foundation</p>
                        <h1 class="page-title">Edit unit {{ $unit->unit_number }}</h1>
                        <p class="page-description">Adjust occupancy and inventory details inside the same property-scoped workspace.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('units.show', $unit) }}">Back to unit</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('units.update', $unit) }}">
                    @method('PUT')
                    @include('units._form', [
                        'cancelUrl' => route('units.show', $unit),
                        'submitLabel' => 'Save changes',
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection