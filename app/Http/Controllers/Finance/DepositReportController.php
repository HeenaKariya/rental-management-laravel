<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\LeaseDeposit;
use App\Models\LeaseDepositEntry;
use App\Models\Property;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepositReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', LeaseDeposit::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        return view('finance.deposit-report', [
            'entryTypes' => LeaseDepositEntry::ENTRY_TYPES,
            'filters' => $filters,
            'properties' => $this->propertiesFor($user),
            'rows' => $rows,
            'statuses' => LeaseDeposit::STATUSES,
            'summary' => $this->summary($rows),
            'user' => $user,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', LeaseDeposit::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        return response()->streamDownload(function () use ($rows, $filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report', 'Deposits']);
            fputcsv($handle, ['Period', $filters['range_label']]);
            fputcsv($handle, ['Status', $filters['status'] === 'all' ? 'All statuses' : str($filters['status'])->replace('_', ' ')->title()->toString()]);
            fputcsv($handle, ['Entry type', $filters['entry_type'] === 'all' ? 'All entry types' : str($filters['entry_type'])->replace('_', ' ')->title()->toString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Property', 'Unit', 'Lease', 'Tenant', 'Status', 'Expected', 'Balance', 'Collected', 'Top Up', 'Deducted', 'Refunded', 'Forfeited', 'Entries']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->lease?->unit?->property?->title,
                    $row->lease?->unit?->unit_number,
                    $row->lease?->lease_number,
                    $row->lease?->tenant?->full_name,
                    str($row->status)->replace('_', ' ')->title()->toString(),
                    (float) $row->expected_amount,
                    (float) $row->current_balance,
                    (float) $row->collected_total,
                    (float) $row->top_up_total,
                    (float) $row->deducted_total,
                    (float) $row->refunded_total,
                    (float) $row->forfeited_total,
                    (int) ($row->entries_count ?? 0),
                ]);
            }

            fclose($handle);
        }, 'deposits-report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request): Response
    {
        $this->authorize('viewAny', LeaseDeposit::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        $pdf = Pdf::loadView('finance.deposit-report-pdf', [
            'filters' => $filters,
            'generatedAt' => now(),
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('deposits-report.pdf');
    }

    private function normalizeFilters(Request $request): array
    {
        $payload = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'status' => ['nullable', 'string', 'in:all,open,settled,forfeited'],
            'entry_type' => ['nullable', 'string', 'in:all,collection,top_up,deduction,refund,forfeiture'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($payload['date_from']) ? Carbon::parse($payload['date_from'])->startOfDay() : null;
        $to = isset($payload['date_to']) ? Carbon::parse($payload['date_to'])->endOfDay() : null;

        return [
            'property_id' => isset($payload['property_id']) ? (int) $payload['property_id'] : null,
            'status' => $payload['status'] ?? 'all',
            'entry_type' => $payload['entry_type'] ?? 'all',
            'date_from' => $from,
            'date_to' => $to,
            'range_label' => $from && $to
                ? sprintf('%s to %s', $from->toDateString(), $to->toDateString())
                : 'All time',
        ];
    }

    private function queryRows(User $user, array $filters): Collection
    {
        return LeaseDeposit::query()
            ->visibleTo($user)
            ->with([
                'lease.tenant',
                'lease.unit.property',
            ])
            ->withCount('entries')
            ->when(
                $filters['property_id'],
                fn ($query) => $query->whereHas('lease.unit', fn ($unitQuery) => $unitQuery->where('property_id', $filters['property_id']))
            )
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                $filters['entry_type'] !== 'all',
                fn ($query) => $query->whereHas('entries', fn ($entryQuery) => $entryQuery->where('entry_type', $filters['entry_type']))
            )
            ->when(
                $filters['date_from'] && $filters['date_to'],
                fn ($query) => $query->whereHas('entries', fn ($entryQuery) => $entryQuery
                    ->whereDate('occurred_at', '>=', $filters['date_from']->toDateString())
                    ->whereDate('occurred_at', '<=', $filters['date_to']->toDateString())
                )
            )
            ->latest('id')
            ->get();
    }

    private function summary(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'expected_total' => round((float) $rows->sum('expected_amount'), 2),
            'balance_total' => round((float) $rows->sum('current_balance'), 2),
            'collected_total' => round((float) $rows->sum('collected_total'), 2),
            'released_total' => round((float) $rows->sum(fn (LeaseDeposit $row) => (float) $row->deducted_total + (float) $row->refunded_total + (float) $row->forfeited_total), 2),
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
