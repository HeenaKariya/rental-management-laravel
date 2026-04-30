<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyLedgerEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        return view('finance.expense-report', [
            'categories' => PropertyLedgerEntry::EXPENSE_CATEGORIES,
            'filters' => $filters,
            'properties' => $this->propertiesFor($user),
            'rows' => $rows,
            'statuses' => PropertyLedgerEntry::STATUSES,
            'summary' => $this->summary($rows),
            'user' => $user,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        return response()->streamDownload(function () use ($rows, $filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report', 'Expenses']);
            fputcsv($handle, ['Period', $filters['range_label']]);
            fputcsv($handle, ['Status', $filters['status'] === 'all' ? 'All statuses' : str($filters['status'])->replace('_', ' ')->title()->toString()]);
            fputcsv($handle, ['Category', $filters['category'] === 'all' ? 'All categories' : str($filters['category'])->replace('_', ' ')->title()->toString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Property', 'Entry date', 'Category', 'Amount', 'Status', 'Vendor', 'Reference', 'Flagged reason']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->property?->title,
                    $row->entry_date?->toDateString(),
                    str($row->category)->replace('_', ' ')->title()->toString(),
                    (float) $row->amount,
                    str($row->status)->replace('_', ' ')->title()->toString(),
                    $row->vendor_name,
                    $row->reference_number,
                    $row->flagged_reason,
                ]);
            }

            fclose($handle);
        }, 'expenses-report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request): Response
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        $pdf = Pdf::loadView('finance.expense-report-pdf', [
            'filters' => $filters,
            'generatedAt' => now(),
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('expenses-report.pdf');
    }

    private function normalizeFilters(Request $request): array
    {
        $payload = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'status' => ['nullable', 'string', 'in:all,approved,pending_review,rejected'],
            'category' => ['nullable', 'string', 'in:all,maintenance,loan_emi,property_tax,insurance,management_fee,utility,other'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($payload['date_from']) ? Carbon::parse($payload['date_from'])->startOfDay() : null;
        $to = isset($payload['date_to']) ? Carbon::parse($payload['date_to'])->endOfDay() : null;

        return [
            'property_id' => isset($payload['property_id']) ? (int) $payload['property_id'] : null,
            'status' => $payload['status'] ?? 'all',
            'category' => $payload['category'] ?? 'all',
            'date_from' => $from,
            'date_to' => $to,
            'range_label' => $from && $to
                ? sprintf('%s to %s', $from->toDateString(), $to->toDateString())
                : 'All time',
        ];
    }

    private function queryRows(User $user, array $filters): Collection
    {
        return PropertyLedgerEntry::query()
            ->visibleTo($user)
            ->with('property')
            ->where('entry_type', 'expense')
            ->when(
                $filters['property_id'],
                fn ($query) => $query->where('property_id', $filters['property_id'])
            )
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                $filters['category'] !== 'all',
                fn ($query) => $query->where('category', $filters['category'])
            )
            ->when(
                $filters['date_from'] && $filters['date_to'],
                fn ($query) => $query
                    ->whereDate('entry_date', '>=', $filters['date_from']->toDateString())
                    ->whereDate('entry_date', '<=', $filters['date_to']->toDateString())
            )
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();
    }

    private function summary(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'amount_total' => round((float) $rows->sum('amount'), 2),
            'pending_review_total' => round((float) $rows->where('status', 'pending_review')->sum('amount'), 2),
            'approved_total' => round((float) $rows->where('status', 'approved')->sum('amount'), 2),
            'high_value_count' => $rows->filter(fn (PropertyLedgerEntry $row) => (float) $row->amount >= 50000)->count(),
        ];
    }

    private function propertiesFor(User $user): Collection
    {
        return Property::query()
            ->visibleTo($user)
            ->orderBy('title', 'asc')
            ->get();
    }
}
