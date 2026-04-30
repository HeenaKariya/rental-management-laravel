<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Rent Collection Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { margin-bottom: 12px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; text-align: left; }
        th { background: #f3f4f6; }
        .summary { margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Rent Collection Report</h1>
    <p class="meta">
        Period: {{ $filters['range_label'] }}<br>
        Payment mode: {{ $filters['payment_mode'] === 'all' ? 'All modes' : str($filters['payment_mode'])->replace('_', ' ')->title() }}<br>
        Generated: {{ $generatedAt->format('M j, Y g:i A') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th>Unit</th>
                <th>Lease</th>
                <th>Tenant</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Late fee</th>
                <th>Mode</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->ledger?->lease?->unit?->property?->title }}</td>
                    <td>{{ $row->ledger?->lease?->unit?->unit_number }}</td>
                    <td>{{ $row->ledger?->lease?->lease_number }}</td>
                    <td>{{ $row->ledger?->lease?->tenant?->full_name }}</td>
                    <td>{{ $row->payment_date?->toDateString() }}</td>
                    <td>{{ number_format((float) $row->amount_paid, 2) }}</td>
                    <td>{{ number_format((float) $row->late_fee_charged, 2) }}</td>
                    <td>{{ str($row->payment_mode)->replace('_', ' ')->title() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No rent collections matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Instalments:</strong> {{ $summary['count'] }}<br>
        <strong>Amount paid total:</strong> {{ number_format($summary['amount_paid_total'], 2) }}<br>
        <strong>Late fee total:</strong> {{ number_format($summary['late_fee_total'], 2) }}<br>
        <strong>Cash total:</strong> {{ number_format($summary['cash_total'], 2) }}<br>
        <strong>Digital total:</strong> {{ number_format($summary['digital_total'], 2) }}
    </div>
</body>
</html>
