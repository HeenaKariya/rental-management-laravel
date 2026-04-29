<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rent Return Summary</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #14213d; font-size: 12px; }
        h1, h2, h3, p { margin: 0 0 8px; }
        .section { margin-bottom: 18px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td, .grid th { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }
        .muted { color: #475569; }
    </style>
</head>
<body>
    <div class="section">
        <h1>Rent Return Summary</h1>
        <p class="muted">Lease {{ $lease->lease_number }} · {{ $lease->tenant->full_name }}</p>
        <p class="muted">{{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}</p>
    </div>

    <div class="section">
        <table class="grid">
            <tr>
                <th>Status</th>
                <td>{{ str($rentReturn->status)->replace('_', ' ')->title() }}</td>
                <th>Vacation date</th>
                <td>{{ $rentReturn->vacation_date->format('M j, Y') }}</td>
            </tr>
            <tr>
                <th>Paid-through date</th>
                <td>{{ $rentReturn->last_paid_through_date?->format('M j, Y') ?: 'Not recorded' }}</td>
                <th>Unused days</th>
                <td>{{ $rentReturn->unused_days }}</td>
            </tr>
            <tr>
                <th>Suggested amount</th>
                <td>{{ number_format((float) $rentReturn->suggested_amount, 2) }}</td>
                <th>Confirmed amount</th>
                <td>{{ $rentReturn->confirmed_amount !== null ? number_format((float) $rentReturn->confirmed_amount, 2) : 'Pending' }}</td>
            </tr>
            <tr>
                <th>Settlement</th>
                <td>{{ $rentReturn->settlement_method ? str($rentReturn->settlement_method)->replace('_', ' ')->title() : 'Not recorded' }}</td>
                <th>Ledger posting</th>
                <td>{{ $rentReturn->ledger_posted ? 'Requested' : 'Not requested' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Notes</h3>
        <p>{{ $rentReturn->notes ?: 'No notes recorded.' }}</p>
        @if ($rentReturn->override_reason)
            <p><strong>Override reason:</strong> {{ $rentReturn->override_reason }}</p>
        @endif
        @if ($rentReturn->settlement_details)
            <p><strong>Settlement details:</strong> {{ $rentReturn->settlement_details }}</p>
        @endif
    </div>
</body>
</html>