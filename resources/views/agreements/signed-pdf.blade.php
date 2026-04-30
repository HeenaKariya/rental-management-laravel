<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Signed Lease Agreement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { margin-bottom: 14px; color: #4b5563; }
        .content { border: 1px solid #d1d5db; padding: 12px; margin: 12px 0; }
        .signature { margin-top: 16px; border-top: 1px dashed #9ca3af; padding-top: 10px; }
    </style>
</head>
<body>
    <h1>Signed Lease Agreement</h1>
    <p class="meta">
        Lease: {{ $agreement->lease->lease_number }}<br>
        Property: {{ $agreement->lease->unit->property->title }} · {{ $agreement->lease->unit->unit_number }}<br>
        Tenant: {{ $agreement->tenant->full_name }}
    </p>

    <div class="content">{!! $agreement->generated_content !!}</div>

    <div class="signature">
        Signed by: {{ $signatureLabel }}<br>
        Signed at: {{ $signedAt->format('M j, Y g:i A') }}<br>
        Signature method: Typed name
    </div>

    <p class="meta" style="margin-top: 16px;">
        This digital agreement is for initial acceptance only and does not replace a government-approved notarized rent agreement, which must be executed separately as per applicable law.
    </p>
</body>
</html>