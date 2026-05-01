@extends('layouts.app', ['title' => 'Sale Lifecycle | PropMgr'])

@section('content')
    <div class="">
        <div class="">
            <div class="d-flex flex-column gap-3 property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 5 finance</p>
                        <h1 class="page-title">Sale lifecycle · {{ $property->title }}</h1>
                        <p class="page-description">Listing data, lead tracking, sale closure, and per-owner profit/loss distribution.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('properties.show', $property) }}">Back to property</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status"><span class="badge badge-green">{{ session('status') }}</span></div>
                @endif

                <section class="row g-3">
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Status</p>
                        <h2 class="stat-value">{{ $sale ? str($sale->status)->replace('_', ' ')->title() : 'Not listed' }}</h2>
                        <p class="stat-meta"><span>property lifecycle {{ str($property->lifecycle_stage)->replace('_', ' ')->title() }}</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Asking price</p>
                        <h2 class="stat-value">{{ number_format((float) ($sale?->asking_price ?? 0), 2) }}</h2>
                        <p class="stat-meta"><span>listing benchmark</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Net sale proceeds</p>
                        <h2 class="stat-value">{{ number_format((float) ($sale?->net_sale_proceeds ?? 0), 2) }}</h2>
                        <p class="stat-meta"><span>after commission and closing costs</span></p>
                    </article>
                    <article class="card shadow-sm h-100 p-3">
                        <p class="stat-label">Gross profit / loss</p>
                        <h2 class="stat-value">{{ number_format((float) ($sale?->gross_profit_loss ?? 0), 2) }}</h2>
                        <p class="stat-meta"><span>vs acquisition {{ number_format((float) ($sale?->total_acquisition_cost_snapshot ?? ($property->purchase?->total_acquisition_cost ?? 0)), 2) }}</span></p>
                    </article>
                </section>

                <section class="row g-3">
                    <div class="col-12 col-xl-8 d-flex flex-column gap-3">
                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Lead and offer log</p>
                                    <h3 class="dashboard-panel-title">Prospect interactions</h3>
                                </div>
                            </div>

                            @if (! $sale || $sale->leads->isEmpty())
                                <p class="security-empty">No leads logged yet.</p>
                            @else
                                <div class="">
                                    <table class="data-table data-table-compact table w-100">
                                        <thead>
                                            <tr>
                                                <th>Buyer</th>
                                                <th>Inquiry</th>
                                                <th>Offer</th>
                                                <th>Status</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($sale->leads as $lead)
                                                <tr>
                                                    <td>
                                                        <div class="data-table-primary">{{ $lead->buyer_name }}</div>
                                                        <div class="data-table-secondary">{{ $lead->buyer_contact ?: 'No contact' }}</div>
                                                    </td>
                                                    <td>{{ $lead->inquiry_date->format('M j, Y') }}</td>
                                                    <td>
                                                        {{ $lead->offer_amount !== null ? number_format((float) $lead->offer_amount, 2) : 'No offer' }}
                                                        @if ($lead->offer_date)
                                                            <br><span class="muted-text">{{ $lead->offer_date->format('M j, Y') }}</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ str($lead->status)->replace('_', ' ')->title() }}</td>
                                                    <td>{{ $lead->notes ?: 'No notes' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>

                        <article class="card border-0 shadow-sm dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Owner distribution</p>
                                    <h3 class="dashboard-panel-title">Profit / loss share</h3>
                                </div>
                            </div>

                            @if ($ownerShares === [])
                                <p class="security-empty">Owner share distribution appears after sale closure and ownership setup.</p>
                            @else
                                <div class="">
                                    <table class="data-table data-table-compact table w-100">
                                        <thead>
                                            <tr>
                                                <th>Owner</th>
                                                <th>Ownership</th>
                                                <th>Share amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($ownerShares as $ownerShare)
                                                <tr>
                                                    <td>{{ $ownerShare['owner']->user?->name ?: $ownerShare['owner']->owner_name }}</td>
                                                    <td>{{ number_format((float) $ownerShare['owner']->ownership_pct, 2) }}%</td>
                                                    <td>{{ number_format((float) $ownerShare['share_amount'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    </div>

                    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
                        @can('update', $property)
                            <article class="card border-0 shadow-sm dashboard-panel">
                                <div class="dashboard-panel-head">
                                    <div>
                                        <p class="row-label">Listing</p>
                                        <h3 class="dashboard-panel-title">Create / update listing</h3>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('properties.finance.sale.store', $property) }}">
                                    @csrf
                                    <label class="field-group"><span class="field-label">Listing date</span><input class="field-input" type="date" name="listing_date" value="{{ old('listing_date', $sale?->listing_date?->toDateString()) }}" required></label>
                                    <label class="field-group"><span class="field-label">Asking price</span><input class="field-input" type="number" min="0" step="0.01" name="asking_price" value="{{ old('asking_price', $sale?->asking_price) }}" required></label>
                                    <label class="field-group"><span class="field-label">Broker name</span><input class="field-input" type="text" name="broker_name" value="{{ old('broker_name', $sale?->broker_name) }}"></label>
                                    <label class="field-group"><span class="field-label">Broker contact</span><input class="field-input" type="text" name="broker_contact" value="{{ old('broker_contact', $sale?->broker_contact) }}"></label>
                                    <label class="field-group"><span class="field-label">Listing notes</span><textarea class="field-input" name="listing_notes" rows="3">{{ old('listing_notes', $sale?->listing_notes) }}</textarea></label>
                                    <button class="btn btn-primary" type="submit">Save listing</button>
                                </form>
                            </article>

                            @if ($sale)
                                <article class="card border-0 shadow-sm dashboard-panel">
                                    <div class="dashboard-panel-head">
                                        <div>
                                            <p class="row-label">Lead log</p>
                                            <h3 class="dashboard-panel-title">Add buyer interaction</h3>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('properties.finance.sale.leads.store', [$property, $sale]) }}">
                                        @csrf
                                        <label class="field-group"><span class="field-label">Buyer name</span><input class="field-input" type="text" name="buyer_name" required></label>
                                        <label class="field-group"><span class="field-label">Buyer contact</span><input class="field-input" type="text" name="buyer_contact"></label>
                                        <label class="field-group"><span class="field-label">Inquiry date</span><input class="field-input" type="date" name="inquiry_date" value="{{ now()->toDateString() }}" required></label>
                                        <label class="field-group"><span class="field-label">Offer amount</span><input class="field-input" type="number" min="0" step="0.01" name="offer_amount"></label>
                                        <label class="field-group"><span class="field-label">Offer date</span><input class="field-input" type="date" name="offer_date"></label>
                                        <label class="field-group">
                                            <span class="field-label">Status</span>
                                            <select class="field-input" name="status" required>
                                                @foreach ($leadStatusOptions as $statusOption)
                                                    <option value="{{ $statusOption }}">{{ str($statusOption)->replace('_', ' ')->title() }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="field-group"><span class="field-label">Notes</span><textarea class="field-input" name="notes" rows="2"></textarea></label>
                                        <button class="btn btn-primary" type="submit">Log lead</button>
                                    </form>
                                </article>

                                @if ($sale->status !== 'closed')
                                    <article class="card border-0 shadow-sm dashboard-panel">
                                        <div class="dashboard-panel-head">
                                            <div>
                                                <p class="row-label">Sale closure</p>
                                                <h3 class="dashboard-panel-title">Finalize sale</h3>
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('properties.finance.sale.close', [$property, $sale]) }}" enctype="multipart/form-data">
                                            @csrf
                                            <label class="field-group"><span class="field-label">Final sale price</span><input class="field-input" type="number" min="0" step="0.01" name="final_sale_price" required></label>
                                            <label class="field-group"><span class="field-label">Sale date</span><input class="field-input" type="date" name="sale_date" value="{{ now()->toDateString() }}" required></label>
                                            <label class="field-group"><span class="field-label">Buyer name</span><input class="field-input" type="text" name="buyer_name" required></label>
                                            <label class="field-group"><span class="field-label">Buyer contact</span><input class="field-input" type="text" name="buyer_contact"></label>
                                            <label class="field-group"><span class="field-label">Broker commission</span><input class="field-input" type="number" min="0" step="0.01" name="broker_commission" value="0"></label>
                                            <label class="field-group"><span class="field-label">Closing costs</span><input class="field-input" type="number" min="0" step="0.01" name="closing_costs" value="0"></label>
                                            <label class="field-group"><span class="field-label">Sale deed upload</span><input class="field-input" type="file" name="sale_deed"></label>
                                            <label class="field-group"><span class="field-label">Sale notes</span><textarea class="field-input" name="sale_notes" rows="2"></textarea></label>
                                            <button class="btn btn-outline-danger" type="submit">Close sale and mark sold</button>
                                        </form>
                                    </article>
                                @endif
                            @endif
                        @else
                            <article class="card border-0 shadow-sm dashboard-panel"><p class="security-empty">Read-only visibility is enabled for your role in this workspace.</p></article>
                        @endcan
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection