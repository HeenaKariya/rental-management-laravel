<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Arrears Report</title>
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
    <h1>Arrears and Partial Payments Report</h1>
    <p class="meta">
        Period: {{ $filters['range_label'] }}<br>
        Alert threshold: {{ $filters['alert_threshold_months'] }} month(s)<br>
        Generated: {{ $generatedAt->format('M j, Y g:i A') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th>Unit</th>
                <th>Lease</th>
                <th>Tenant</th>
                <th>Month</th>
                <th>Status</th>
                <th>Carried arrears</th>
                <th>Outstanding</th>
                <th>Instalments</th>
                <th>Instalments paid</th>
                <th>Alert</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->lease?->unit?->property?->title }}</td>
                    <td>{{ $row->lease?->unit?->unit_number }}</td>
                    <td>{{ $row->lease?->lease_number }}</td>
                    <td>{{ $row->lease?->tenant?->full_name }}</td>
                    <td>{{ $row->payment_month?->toDateString() }}</td>
                    <td>{{ str($row->status)->replace('_', ' ')->title() }}</td>
                    <td>{{ number_format((float) $row->carried_arrears, 2) }}</td>
                    <td>{{ number_format((float) $row->outstanding_balance, 2) }}</td>
                    <td>{{ (int) ($row->instalments_count ?? 0) }}</td>
                    <td>{{ number_format((float) ($row->instalments_paid_total ?? 0), 2) }}</td>
                    <td>{{ $row->arrears_alert ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">No arrears rows matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Rows:</strong> {{ $summary['count'] }}<br>
        <strong>Outstanding total:</strong> {{ number_format($summary['outstanding_total'], 2) }}<br>
        <strong>Carried arrears total:</strong> {{ number_format($summary['carried_arrears_total'], 2) }}<br>
        <strong>Partial count:</strong> {{ $summary['partial_count'] }}<br>
        <strong>Overdue count:</strong> {{ $summary['overdue_count'] }}<br>
        <strong>Alerted leases:</strong> {{ $summary['alerted_count'] }}
    </div>
</body>
</html>
