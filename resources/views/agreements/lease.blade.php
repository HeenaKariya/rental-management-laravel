@extends('layouts.app', ['title' => 'Lease Agreement | PropMgr'])

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <div class="dashboard-stack property-detail-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Phase 6 agreement</p>
                        <h1 class="page-title">Lease agreement · {{ $lease->lease_number }}</h1>
                        <p class="page-description">Generate digital agreement links and monitor signature lifecycle for {{ $lease->tenant->full_name }}.</p>
                    </div>
                    <div class="page-actions">
                        <a class="btn btn-ghost" href="{{ route('leases.show', $lease) }}">Back to lease</a>
                        <a class="btn btn-ghost" href="{{ route('agreements.templates.index') }}">Templates</a>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status"><span class="badge badge-green">{{ session('status') }}</span></div>
                @endif

                <section class="dashboard-grid">
                    <div class="dashboard-column-wide">
                        <article class="table-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Agreement history</p>
                                    <h3 class="dashboard-panel-title">Generated links and statuses</h3>
                                </div>
                            </div>

                            @if ($lease->agreements->isEmpty())
                                <p class="security-empty">No agreements generated for this lease yet.</p>
                            @else
                                <div class="data-table-card">
                                    <table class="data-table data-table-compact">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Template</th>
                                                <th>Generated</th>
                                                <th>Signed</th>
                                                <th>Signed PDF</th>
                                                <th>Integrity</th>
                                                <th>Public link</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($lease->agreements as $agreement)
                                                <tr>
                                                    <td>{{ str($agreement->status)->replace('_', ' ')->title() }}</td>
                                                    <td>{{ $agreement->template?->name ?: 'Template removed' }}</td>
                                                    <td>{{ $agreement->created_at->format('M j, Y g:i A') }}</td>
                                                    <td>{{ $agreement->signed_at?->format('M j, Y g:i A') ?: 'Not signed' }}</td>
                                                    <td>
                                                        @if ($agreement->signed_pdf_path)
                                                            <a href="{{ route('leases.agreement.download-signed-pdf', [$lease, $agreement]) }}">Download</a>
                                                        @else
                                                            Not available
                                                        @endif
                                                    </td>
                                                    <td>
                                                        {{ $agreement->integrity_check_status ? str($agreement->integrity_check_status)->title() : 'Not checked' }}
                                                        @if ($agreement->integrity_last_checked_at)
                                                            <br><span class="muted-text">{{ $agreement->integrity_last_checked_at->format('M j, g:i A') }}</span>
                                                        @endif
                                                        @if ($agreement->integrity_check_notes)
                                                            <br><span class="muted-text">{{ $agreement->integrity_check_notes }}</span>
                                                        @endif
                                                        @can('update', $lease)
                                                            @if ($agreement->status === 'signed')
                                                                <form method="POST" action="{{ route('leases.agreement.verify-integrity', [$lease, $agreement]) }}" style="margin-top: 0.5rem;">
                                                                    @csrf
                                                                    <button class="btn btn-ghost btn-sm" type="submit">Verify</button>
                                                                </form>
                                                            @endif
                                                        @endcan
                                                    </td>
                                                    <td><a href="{{ route('agreements.public.show', $agreement->token) }}" target="_blank" rel="noopener">Open</a></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    </div>

                    <div class="dashboard-column-side">
                        <article class="security-card dashboard-panel">
                            <div class="dashboard-panel-head">
                                <div>
                                    <p class="row-label">Generate</p>
                                    <h3 class="dashboard-panel-title">Create digital agreement</h3>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('leases.agreement.store', $lease) }}">
                                @csrf
                                <label class="field-group">
                                    <span class="field-label">Template</span>
                                    <select class="field-input" name="template_id" required>
                                        @foreach ($activeTemplates as $template)
                                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field-group">
                                    <span class="field-label">Manual content override (optional)</span>
                                    <textarea class="field-input" name="manual_content" rows="6" placeholder="Leave empty to use resolved template placeholders."></textarea>
                                </label>
                                <button class="btn btn-solid" type="submit">Generate agreement</button>
                            </form>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection