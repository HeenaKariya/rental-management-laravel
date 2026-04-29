<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Rent Receipt</title>
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                color: #1f2937;
                font-size: 12px;
                line-height: 1.5;
            }

            .shell {
                border: 1px solid #d1d5db;
                padding: 28px;
            }

            .header {
                border-bottom: 2px solid #111827;
                margin-bottom: 18px;
                padding-bottom: 14px;
            }

            .eyebrow {
                color: #6b7280;
                font-size: 11px;
                letter-spacing: 0.08em;
                margin: 0 0 4px;
                text-transform: uppercase;
            }

            h1 {
                font-size: 24px;
                margin: 0;
            }

            .grid {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 18px;
            }

            .grid td {
                padding: 8px 10px;
                vertical-align: top;
                width: 50%;
            }

            .label {
                color: #6b7280;
                display: block;
                font-size: 10px;
                margin-bottom: 3px;
                text-transform: uppercase;
            }

            .value {
                font-size: 14px;
                font-weight: 600;
            }

            .summary {
                width: 100%;
                border-collapse: collapse;
            }

            .summary th,
            .summary td {
                border: 1px solid #d1d5db;
                padding: 10px;
                text-align: left;
            }

            .summary th {
                background: #f3f4f6;
                font-size: 11px;
                text-transform: uppercase;
            }

            .footnote {
                color: #6b7280;
                font-size: 10px;
                margin-top: 18px;
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <div class="header">
                <p class="eyebrow">PropMgr Rent Receipt</p>
                <h1>Instalment {{ $instalment->instalment_number }}</h1>
            </div>

            <table class="grid">
                <tr>
                    <td>
                        <span class="label">Property and Unit</span>
                        <span class="value">{{ $lease->unit->property->title }} · {{ $lease->unit->unit_number }}</span>
                    </td>
                    <td>
                        <span class="label">Tenant</span>
                        <span class="value">{{ $lease->tenant->full_name }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="label">Lease Period</span>
                        <span class="value">{{ $lease->start_on->format('M j, Y') }} to {{ $lease->end_on->format('M j, Y') }}</span>
                    </td>
                    <td>
                        <span class="label">Payment Month</span>
                        <span class="value">{{ $ledger->payment_month->format('F Y') }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="label">Payment Mode</span>
                        <span class="value">{{ str($instalment->payment_mode)->replace('_', ' ')->title() }}</span>
                    </td>
                    <td>
                        <span class="label">Reference Number</span>
                        <span class="value">{{ $instalment->reference_number ?: 'N/A' }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="label">Recorded By</span>
                        <span class="value">{{ $instalment->recorder?->name ?: 'System' }}</span>
                    </td>
                    <td>
                        <span class="label">Recorded At</span>
                        <span class="value">{{ $instalment->created_at?->format('M j, Y g:i A') ?: 'N/A' }}</span>
                    </td>
                </tr>
            </table>

            <table class="summary">
                <thead>
                    <tr>
                        <th>Amount Paid</th>
                        <th>Late Fee</th>
                        <th>Cumulative Paid</th>
                        <th>Outstanding Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ number_format((float) $instalment->amount_paid, 2) }}</td>
                        <td>{{ number_format((float) $instalment->late_fee_charged, 2) }}</td>
                        <td>{{ number_format((float) $cumulativeAmountPaid, 2) }}</td>
                        <td>{{ number_format((float) $remainingOutstanding, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="footnote">
                Lease {{ $lease->lease_number }} · Due {{ $ledger->due_on->format('M j, Y') }} ·
                Total due {{ number_format((float) $ledger->total_due, 2) }} ·
                Late fees to date {{ number_format((float) $ledger->late_fee_total, 2) }}.
            </p>
        </div>
    </body>
</html>