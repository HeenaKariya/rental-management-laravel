<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Deposits Report</title>
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
    <h1>Deposits Report</h1>
    <p class="meta">
        Period: {{ $filters['range_label'] }}<br>
        Status: {{ $filters['status'] === 'all' ? 'All statuses' : str($filters['status'])->replace('_', ' ')->title() }}<br>
        Entry type: {{ $filters['entry_type'] === 'all' ? 'All entry types' : str($filters['entry_type'])->replace('_', ' ')->title() }}<br>
        Generated: {{ $generatedAt->format('M j, Y g:i A') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th>Unit</th>
                <th>Lease</th>
                <th>Tenant</th>
                <th>Status</th>
                <th>Expected</th>
                <th>Balance</th>
                <th>Collected</th>
                <th>Released</th>
                <th>Entries</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->lease?->unit?->property?->title }}</td>
                    <td>{{ $row->lease?->unit?->unit_number }}</td>
                    <td>{{ $row->lease?->lease_number }}</td>
                    <td>{{ $row->lease?->tenant?->full_name }}</td>
                    <td>{{ str($row->status)->replace('_', ' ')->title() }}</td>
                    <td>{{ number_format((float) $row->expected_amount, 2) }}</td>
                    <td>{{ number_format((float) $row->current_balance, 2) }}</td>
                    <td>{{ number_format((float) $row->collected_total + (float) $row->top_up_total, 2) }}</td>
                    <td>{{ number_format((float) $row->deducted_total + (float) $row->refunded_total + (float) $row->forfeited_total, 2) }}</td>
                    <td>{{ (int) ($row->entries_count ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No deposit accounts matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Accounts:</strong> {{ $summary['count'] }}<br>
        <strong>Expected total:</strong> {{ number_format($summary['expected_total'], 2) }}<br>
        <strong>Held balance:</strong> {{ number_format($summary['balance_total'], 2) }}<br>
        <strong>Collected total:</strong> {{ number_format($summary['collected_total'], 2) }}<br>
        <strong>Released total:</strong> {{ number_format($summary['released_total'], 2) }}
    </div>
</body>
</html>
