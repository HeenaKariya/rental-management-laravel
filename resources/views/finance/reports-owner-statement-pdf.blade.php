<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Owner Statement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { margin-bottom: 14px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .summary { margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Owner Statement</h1>
    <p class="meta">Property: {{ $property->title }}<br>Period: {{ $filters['range_label'] }}<br>Generated: {{ $generatedAt->format('M j, Y g:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th>Owner</th>
                <th>Ownership %</th>
                <th>Income Share</th>
                <th>Expense Share</th>
                <th>Net Ops Share</th>
                <th>Sale P/L Share</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ownerStatementRows as $row)
                <tr>
                    <td>{{ $row['owner_name'] }}</td>
                    <td>{{ number_format((float) $row['ownership_pct'], 2) }}%</td>
                    <td>{{ number_format((float) $row['income_share'], 2) }}</td>
                    <td>{{ number_format((float) $row['expense_share'], 2) }}</td>
                    <td>{{ number_format((float) $row['net_operational_share'], 2) }}</td>
                    <td>{{ number_format((float) $row['sale_share'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No active ownership rows found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <strong>Operational income:</strong> {{ number_format((float) $summary['total_income'], 2) }}<br>
        <strong>Operational expense:</strong> {{ number_format((float) $summary['total_expense'], 2) }}<br>
        <strong>Net operational income:</strong> {{ number_format((float) $summary['net_operational_income'], 2) }}<br>
        <strong>Sale profit/loss:</strong> {{ number_format((float) $summary['sale_profit_loss'], 2) }}
    </div>
</body>
</html>