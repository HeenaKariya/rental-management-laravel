<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Expense Report</title>
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
    <h1>Expense Report</h1>
    <p class="meta">
        Period: {{ $filters['range_label'] }}<br>
        Status: {{ $filters['status'] === 'all' ? 'All statuses' : str($filters['status'])->replace('_', ' ')->title() }}<br>
        Category: {{ $filters['category'] === 'all' ? 'All categories' : str($filters['category'])->replace('_', ' ')->title() }}<br>
        Generated: {{ $generatedAt->format('M j, Y g:i A') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th>Date</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Vendor</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->property?->title }}</td>
                    <td>{{ $row->entry_date?->toDateString() }}</td>
                    <td>{{ str($row->category)->replace('_', ' ')->title() }}</td>
                    <td>{{ number_format((float) $row->amount, 2) }}</td>
                    <td>{{ str($row->status)->replace('_', ' ')->title() }}</td>
                    <td>{{ $row->vendor_name }}</td>
                    <td>{{ $row->reference_number }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No expense entries matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Rows:</strong> {{ $summary['count'] }}<br>
        <strong>Total expense:</strong> {{ number_format($summary['amount_total'], 2) }}<br>
        <strong>Approved total:</strong> {{ number_format($summary['approved_total'], 2) }}<br>
        <strong>Pending review total:</strong> {{ number_format($summary['pending_review_total'], 2) }}<br>
        <strong>High value rows:</strong> {{ $summary['high_value_count'] }}
    </div>
</body>
</html>
