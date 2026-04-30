<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyReportController extends Controller
{
    public function show(Request $request, Property $property)
    {
        $this->authorize('view', $property);

        $property->loadMissing(['owners.user', 'purchase', 'loan', 'sale']);
        $filters = $this->normalizeFilters($request);

        $entries = $this->approvedLedgerEntries($property, $filters);
        $operationalSummary = $this->operationalSummary($property, $filters, $entries);
        $ownerStatementRows = $this->ownerStatementRows($property, $operationalSummary);
        $pnlMatrixRows = $this->pnlMatrixRows($entries);

        return view('finance.reports', [
            'filters' => $filters,
            'ownerStatementRows' => $ownerStatementRows,
            'operationalSummary' => $operationalSummary,
            'pnlMatrixRows' => $pnlMatrixRows,
            'property' => $property,
            'user' => $request->user(),
        ]);
    }

    public function ownerStatementCsv(Request $request, Property $property): StreamedResponse
    {
        $this->authorize('view', $property);

        $property->loadMissing(['owners.user', 'sale']);
        $filters = $this->normalizeFilters($request);
        $entries = $this->approvedLedgerEntries($property, $filters);
        $summary = $this->operationalSummary($property, $filters, $entries);
        $rows = $this->ownerStatementRows($property, $summary);

        $filename = sprintf('owner-statement-%s.csv', $property->id);

        return response()->streamDownload(function () use ($rows, $property, $summary, $filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Property', $property->title]);
            fputcsv($handle, ['Report', 'Owner Statement']);
            fputcsv($handle, ['Period', $filters['range_label']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Owner', 'Ownership %', 'Income Share', 'Expense Share', 'Net Ops Share', 'Sale P/L Share']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['owner_name'],
                    $row['ownership_pct'],
                    $row['income_share'],
                    $row['expense_share'],
                    $row['net_operational_share'],
                    $row['sale_share'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Operational income', $summary['total_income']]);
            fputcsv($handle, ['Operational expense', $summary['total_expense']]);
            fputcsv($handle, ['Net operational income', $summary['net_operational_income']]);
            fputcsv($handle, ['Sale gross profit/loss', $summary['sale_profit_loss']]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function ownerStatementPdf(Request $request, Property $property): Response
    {
        $this->authorize('view', $property);

        $property->loadMissing(['owners.user', 'sale']);
        $filters = $this->normalizeFilters($request);
        $entries = $this->approvedLedgerEntries($property, $filters);
        $summary = $this->operationalSummary($property, $filters, $entries);
        $rows = $this->ownerStatementRows($property, $summary);

        $pdf = Pdf::loadView('finance.reports-owner-statement-pdf', [
            'filters' => $filters,
            'generatedAt' => now(),
            'ownerStatementRows' => $rows,
            'property' => $property,
            'summary' => $summary,
        ])->setPaper('a4');

        return $pdf->download(sprintf('owner-statement-%s.pdf', $property->id));
    }

    public function pnlMatrixCsv(Request $request, Property $property): StreamedResponse
    {
        $this->authorize('view', $property);

        $filters = $this->normalizeFilters($request);
        $entries = $this->approvedLedgerEntries($property, $filters);
        $matrixRows = $this->pnlMatrixRows($entries);
        $filename = sprintf('pnl-matrix-%s.csv', $property->id);

        return response()->streamDownload(function () use ($matrixRows, $property, $filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Property', $property->title]);
            fputcsv($handle, ['Report', 'P&L Matrix']);
            fputcsv($handle, ['Period', $filters['range_label']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Category', 'Income', 'Expense', 'Net']);

            foreach ($matrixRows as $row) {
                fputcsv($handle, [
                    $row['category'],
                    $row['income_amount'],
                    $row['expense_amount'],
                    $row['net_amount'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pnlMatrixPdf(Request $request, Property $property): Response
    {
        $this->authorize('view', $property);

        $filters = $this->normalizeFilters($request);
        $entries = $this->approvedLedgerEntries($property, $filters);
        $matrixRows = $this->pnlMatrixRows($entries);
        $summary = $this->operationalSummary($property, $filters, $entries);

        $pdf = Pdf::loadView('finance.reports-pnl-matrix-pdf', [
            'filters' => $filters,
            'generatedAt' => now(),
            'matrixRows' => $matrixRows,
            'property' => $property,
            'summary' => $summary,
        ])->setPaper('a4');

        return $pdf->download(sprintf('pnl-matrix-%s.pdf', $property->id));
    }

    private function operationalSummary(Property $property, array $filters, ?Collection $entries = null): array
    {
        $entries ??= $this->approvedLedgerEntries($property, $filters);

        $totalIncome = (float) $entries
            ->where('entry_type', 'income')
            ->sum('amount');

        $totalExpense = (float) $entries
            ->where('entry_type', 'expense')
            ->sum('amount');

        $saleProfitLoss = (float) ($property->sale?->gross_profit_loss ?? 0);
        if ($filters['date_from'] && $filters['date_to']) {
            $saleDate = $property->sale?->sale_date;
            if (! $saleDate || $saleDate->lt($filters['date_from']) || $saleDate->gt($filters['date_to'])) {
                $saleProfitLoss = 0;
            }
        }

        return [
            'net_operational_income' => round($totalIncome - $totalExpense, 2),
            'sale_profit_loss' => round($saleProfitLoss, 2),
            'total_expense' => round($totalExpense, 2),
            'total_income' => round($totalIncome, 2),
        ];
    }

    private function ownerStatementRows(Property $property, array $summary): array
    {
        $owners = $property->owners()->where('is_active', true)->with('user')->get();

        return $owners->map(function ($owner) use ($summary): array {
            $ownership = (float) $owner->ownership_pct;
            $ratio = $ownership / 100;

            return [
                'expense_share' => round($summary['total_expense'] * $ratio, 2),
                'income_share' => round($summary['total_income'] * $ratio, 2),
                'net_operational_share' => round($summary['net_operational_income'] * $ratio, 2),
                'owner_name' => $owner->user?->name ?: $owner->owner_name,
                'ownership_pct' => $ownership,
                'sale_share' => round($summary['sale_profit_loss'] * $ratio, 2),
            ];
        })->values()->all();
    }

    private function pnlMatrixRows(Collection $entries): array
    {
        /** @var Collection<string, \Illuminate\Support\Collection<int, mixed>> $grouped */
        $grouped = $entries->groupBy('category');

        return $grouped
            ->map(function (Collection $categoryEntries, string $category): array {
                $incomeAmount = (float) $categoryEntries
                    ->where('entry_type', 'income')
                    ->sum('amount');
                $expenseAmount = (float) $categoryEntries
                    ->where('entry_type', 'expense')
                    ->sum('amount');

                return [
                    'category' => str($category)->replace('_', ' ')->title()->toString(),
                    'expense_amount' => round($expenseAmount, 2),
                    'income_amount' => round($incomeAmount, 2),
                    'net_amount' => round($incomeAmount - $expenseAmount, 2),
                ];
            })
            ->sortBy('category')
            ->values()
            ->all();
    }

    private function approvedLedgerEntries(Property $property, array $filters): Collection
    {
        return $property->ledgerEntries()
            ->where('status', 'approved')
            ->when(
                $filters['date_from'] && $filters['date_to'],
                fn ($query) => $query
                    ->whereDate('entry_date', '>=', $filters['date_from']->toDateString())
                    ->whereDate('entry_date', '<=', $filters['date_to']->toDateString())
            )
            ->get(['entry_type', 'category', 'amount', 'entry_date']);
    }

    private function normalizeFilters(Request $request): array
    {
        $payload = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'period' => ['nullable', 'string', 'in:all_time,this_month,last_month,this_quarter,last_quarter,ytd,custom'],
        ]);

        $period = $payload['period'] ?? 'all_time';
        $today = now();
        $from = null;
        $to = null;

        if ($period === 'custom') {
            $from = isset($payload['date_from']) ? Carbon::parse($payload['date_from'])->startOfDay() : null;
            $to = isset($payload['date_to']) ? Carbon::parse($payload['date_to'])->endOfDay() : null;
        }

        if ($period === 'this_month') {
            $from = $today->copy()->startOfMonth();
            $to = $today->copy()->endOfMonth();
        }

        if ($period === 'last_month') {
            $from = $today->copy()->subMonthNoOverflow()->startOfMonth();
            $to = $today->copy()->subMonthNoOverflow()->endOfMonth();
        }

        if ($period === 'this_quarter') {
            $from = $today->copy()->startOfQuarter();
            $to = $today->copy()->endOfQuarter();
        }

        if ($period === 'last_quarter') {
            $reference = $today->copy()->subQuarter();
            $from = $reference->startOfQuarter();
            $to = $reference->endOfQuarter();
        }

        if ($period === 'ytd') {
            $from = $today->copy()->startOfYear();
            $to = $today->copy()->endOfDay();
        }

        return [
            'date_from' => $from,
            'date_to' => $to,
            'period' => $period,
            'range_label' => $this->rangeLabel($period, $from, $to),
        ];
    }

    private function rangeLabel(string $period, ?Carbon $from, ?Carbon $to): string
    {
        if (! $from || ! $to) {
            return 'All time';
        }

        if ($period === 'custom') {
            return sprintf('%s to %s', $from->toDateString(), $to->toDateString());
        }

        if ($period === 'ytd') {
            return sprintf('Year to date (%s to %s)', $from->toDateString(), $to->toDateString());
        }

        return sprintf('%s (%s to %s)', str($period)->replace('_', ' ')->title()->toString(), $from->toDateString(), $to->toDateString());
    }
}