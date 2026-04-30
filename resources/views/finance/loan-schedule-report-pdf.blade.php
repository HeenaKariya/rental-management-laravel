<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Loan Schedule Report</title>
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
    <h1>Loan Schedule Report</h1>
    <p class="meta">
        Period: {{ $filters['range_label'] }}<br>
        Generated: {{ $generatedAt->format('M j, Y g:i A') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th>Lender</th>
                <th>EMI #</th>
                <th>Date paid</th>
                <th>Amount paid</th>
                <th>Principal</th>
                <th>Interest</th>
                <th>Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->loan?->property?->title }}</td>
                    <td>{{ $row->loan?->lender_name }}</td>
                    <td>{{ $row->emi_number }}</td>
                    <td>{{ $row->date_paid?->toDateString() }}</td>
                    <td>{{ number_format((float) $row->amount_paid, 2) }}</td>
                    <td>{{ number_format((float) $row->principal_component, 2) }}</td>
                    <td>{{ number_format((float) $row->interest_component, 2) }}</td>
                    <td>{{ number_format((float) $row->outstanding_balance, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No EMI rows matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Rows:</strong> {{ $summary['count'] }}<br>
        <strong>Amount paid total:</strong> {{ number_format($summary['amount_paid_total'], 2) }}<br>
        <strong>Principal total:</strong> {{ number_format($summary['principal_total'], 2) }}<br>
        <strong>Interest total:</strong> {{ number_format($summary['interest_total'], 2) }}
    </div>
</body>
</html>
