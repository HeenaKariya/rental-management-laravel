<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Rent Return Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { margin-bottom: 14px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; }
        th { background: #f3f4f6; }
        .summary { margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Rent Return Report</h1>
    <p class="meta">
        Period: {{ $filters['range_label'] }}<br>
        Generated: {{ $generatedAt->format('M j, Y g:i A') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th>Lease</th>
                <th>Tenant</th>
                <th>Status</th>
                <th>Suggested</th>
                <th>Confirmed</th>
                <th>Settlement</th>
                <th>Amount</th>
                <th>Ledger Posted</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->property?->title }}</td>
                    <td>{{ $row->lease?->lease_number }}</td>
                    <td>{{ $row->tenant?->full_name }}</td>
                    <td>{{ str($row->status)->replace('_', ' ')->title() }}</td>
                    <td>{{ number_format((float) $row->suggested_amount, 2) }}</td>
                    <td>{{ number_format((float) ($row->confirmed_amount ?? 0), 2) }}</td>
                    <td>{{ str((string) ($row->settlement_method ?? 'n/a'))->replace('_', ' ')->title() }}</td>
                    <td>{{ number_format((float) ($row->settlement_amount ?? 0), 2) }}</td>
                    <td>{{ $row->ledger_posted ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No records matched the selected filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Total records:</strong> {{ $summary['count'] }}<br>
        <strong>Suggested total:</strong> {{ number_format($summary['suggested_total'], 2) }}<br>
        <strong>Confirmed total:</strong> {{ number_format($summary['confirmed_total'], 2) }}<br>
        <strong>Settled total:</strong> {{ number_format($summary['settled_total'], 2) }}<br>
        <strong>Pending settlement count:</strong> {{ $summary['pending_count'] }}<br>
        <strong>Ledger posted count:</strong> {{ $summary['posted_count'] }}
    </div>
</body>
</html>
