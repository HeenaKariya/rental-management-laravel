<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Agreement | PropMgr</title>
    @vite(['resources/css/app.css'])
</head>
<body class="app-body">
    <div class="ui-shell">
        <div class="ui-wrap" style="max-width: 920px;">
            <div class="dashboard-stack">
                <section class="page-header card-soft">
                    <div>
                        <p class="page-kicker">Digital agreement</p>
                        <h1 class="page-title">{{ $agreement->lease->unit->property->title }} · {{ $agreement->lease->unit->unit_number }}</h1>
                        <p class="page-description">This digital agreement is for initial acceptance only and does not replace a government-approved notarized rent agreement, which must be executed separately as per applicable law.</p>
                    </div>
                </section>

                @if (session('status'))
                    <div class="page-status"><span class="badge badge-green">{{ session('status') }}</span></div>
                @endif

                <article class="table-card dashboard-panel">
                    <div class="dashboard-panel-head">
                        <div>
                            <p class="row-label">Agreement content</p>
                            <h3 class="dashboard-panel-title">Review before signing</h3>
                        </div>
                    </div>
                    <div class="field-input" style="min-height: 220px; white-space: normal;">{!! $agreement->generated_content !!}</div>
                </article>

                @if (in_array($agreement->status, ['generated', 'viewed'], true))
                    <article class="security-card dashboard-panel">
                        <form method="POST" action="{{ route('agreements.public.sign', $agreement->token) }}">
                            @csrf
                            <label class="field-group">
                                <span class="field-label">Type your full name as signature</span>
                                <input class="field-input" name="signature_label" required>
                            </label>
                            <button class="btn btn-solid" type="submit">Sign agreement</button>
                        </form>
                    </article>
                @else
                    <article class="security-card dashboard-panel"><p class="security-empty">This agreement is already {{ $agreement->status }} and cannot be signed again.</p></article>
                @endif
            </div>
        </div>
    </div>
</body>
</html>