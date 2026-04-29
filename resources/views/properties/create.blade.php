@extends('layouts.app', ['title' => 'Create Property | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Property workspace</p>
                        <h1 class="page-title">Create property</h1>
                        <p class="page-description">Capture the property record and initial manager scope using the same operations layout as the rest of the workspace.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('properties.index') }}">Back to properties</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('properties.store') }}" enctype="multipart/form-data">
                    @include('properties._form', [
                        'cancelUrl' => route('properties.index'),
                        'submitLabel' => 'Create property',
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection
