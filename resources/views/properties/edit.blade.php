@extends('layouts.app', ['title' => 'Edit Property | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Property workspace</p>
                        <h1 class="page-title">Edit property</h1>
                        <p class="page-description">Update property details, media, and lifecycle metadata with the same layout system used across the dashboard and property views.</p>
                    </div>

                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('properties.show', $property) }}">Back to detail</a>
                    </div>
                </section>

                <form method="POST" action="{{ route('properties.update', $property) }}" enctype="multipart/form-data">
                    @method('PUT')
                    @include('properties._form', [
                        'cancelUrl' => route('properties.show', $property),
                        'submitLabel' => 'Save changes',
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection
