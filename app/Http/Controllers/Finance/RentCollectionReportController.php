<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use App\Models\RentInstalment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentCollectionReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        return view('finance.rent-collection-report', [
            'filters' => $filters,
            'paymentModes' => RentInstalment::PAYMENT_MODES,
            'properties' => $this->propertiesFor($user),
            'rows' => $rows,
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
            fputcsv($handle, ['Report', 'Rent Collection']);
            fputcsv($handle, ['Period', $filters['range_label']]);
            fputcsv($handle, ['Payment mode', $filters['payment_mode'] === 'all' ? 'All modes' : str($filters['payment_mode'])->replace('_', ' ')->title()->toString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Property', 'Unit', 'Lease', 'Tenant', 'Payment date', 'Amount paid', 'Late fee', 'Mode', 'Reference']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->ledger?->lease?->unit?->property?->title,
                    $row->ledger?->lease?->unit?->unit_number,
                    $row->ledger?->lease?->lease_number,
                    $row->ledger?->lease?->tenant?->full_name,
                    $row->payment_date?->toDateString(),
                    (float) $row->amount_paid,
                    (float) $row->late_fee_charged,
                    str($row->payment_mode)->replace('_', ' ')->title()->toString(),
                    $row->reference_number,
                ]);
            }

            fclose($handle);
        }, 'rent-collection-report.csv', [
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

        $pdf = Pdf::loadView('finance.rent-collection-report-pdf', [
            'filters' => $filters,
            'generatedAt' => now(),
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('rent-collection-report.pdf');
    }

    private function normalizeFilters(Request $request): array
    {
        $payload = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'payment_mode' => ['nullable', 'string', 'in:all,cash,bank_transfer,cheque,upi,other'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($payload['date_from']) ? Carbon::parse($payload['date_from'])->startOfDay() : null;
        $to = isset($payload['date_to']) ? Carbon::parse($payload['date_to'])->endOfDay() : null;

        return [
            'property_id' => isset($payload['property_id']) ? (int) $payload['property_id'] : null,
            'payment_mode' => $payload['payment_mode'] ?? 'all',
            'date_from' => $from,
            'date_to' => $to,
            'range_label' => $from && $to
                ? sprintf('%s to %s', $from->toDateString(), $to->toDateString())
                : 'All time',
        ];
    }

    private function queryRows(User $user, array $filters): Collection
    {
        return RentInstalment::query()
            ->where('voided_at', null)
            ->with([
                'ledger.lease.tenant',
                'ledger.lease.unit.property',
            ])
            ->whereHas('ledger', fn ($ledgerQuery) => $ledgerQuery->visibleTo($user))
            ->when(
                $filters['property_id'],
                fn ($query) => $query->whereHas('ledger.lease.unit', fn ($unitQuery) => $unitQuery->where('property_id', $filters['property_id']))
            )
            ->when(
                $filters['payment_mode'] !== 'all',
                fn ($query) => $query->where('payment_mode', $filters['payment_mode'])
            )
            ->when(
                $filters['date_from'] && $filters['date_to'],
                fn ($query) => $query
                    ->whereDate('payment_date', '>=', $filters['date_from']->toDateString())
                    ->whereDate('payment_date', '<=', $filters['date_to']->toDateString())
            )
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();
    }

    private function summary(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'amount_paid_total' => round((float) $rows->sum('amount_paid'), 2),
            'late_fee_total' => round((float) $rows->sum('late_fee_charged'), 2),
            'cash_total' => round((float) $rows->where('payment_mode', 'cash')->sum('amount_paid'), 2),
            'digital_total' => round((float) $rows->whereIn('payment_mode', ['bank_transfer', 'upi'])->sum('amount_paid'), 2),
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
