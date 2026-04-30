<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use App\Models\RentReturn;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentReturnReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        return view('finance.rent-return-report', [
            'filters' => $filters,
            'properties' => $this->propertiesFor($user),
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'statuses' => RentReturn::STATUSES,
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
            fputcsv($handle, ['Report', 'Rent Return']);
            fputcsv($handle, ['Period', $filters['range_label']]);
            fputcsv($handle, ['Status', $filters['status'] === 'all' ? 'All statuses' : str($filters['status'])->replace('_', ' ')->title()->toString()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Property', 'Lease', 'Tenant', 'Status', 'Suggested', 'Confirmed', 'Settlement Method', 'Settlement Amount', 'Ledger Posted', 'Initiated At', 'Processed At']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->property?->title,
                    $row->lease?->lease_number,
                    $row->tenant?->full_name,
                    str($row->status)->replace('_', ' ')->title()->toString(),
                    (float) $row->suggested_amount,
                    (float) ($row->confirmed_amount ?? 0),
                    str((string) ($row->settlement_method ?? 'n/a'))->replace('_', ' ')->title()->toString(),
                    (float) ($row->settlement_amount ?? 0),
                    $row->ledger_posted ? 'Yes' : 'No',
                    $row->initiated_at?->format('Y-m-d H:i:s'),
                    $row->processed_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 'rent-return-report.csv', [
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

        $pdf = Pdf::loadView('finance.rent-return-report-pdf', [
            'filters' => $filters,
            'generatedAt' => now(),
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('rent-return-report.pdf');
    }

    private function normalizeFilters(Request $request): array
    {
        $payload = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'status' => ['nullable', 'string', 'in:all,initiated,confirmed,settled,waived,pending_settlement'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($payload['date_from']) ? Carbon::parse($payload['date_from'])->startOfDay() : null;
        $to = isset($payload['date_to']) ? Carbon::parse($payload['date_to'])->endOfDay() : null;

        return [
            'property_id' => isset($payload['property_id']) ? (int) $payload['property_id'] : null,
            'status' => $payload['status'] ?? 'all',
            'date_from' => $from,
            'date_to' => $to,
            'range_label' => $from && $to
                ? sprintf('%s to %s', $from->toDateString(), $to->toDateString())
                : 'All time',
        ];
    }

    private function queryRows(User $user, array $filters): Collection
    {
        return RentReturn::query()
            ->visibleTo($user)
            ->with(['lease', 'property', 'tenant'])
            ->when($filters['property_id'], fn ($query) => $query->where('property_id', $filters['property_id']))
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                $filters['date_from'] && $filters['date_to'],
                fn ($query) => $query
                    ->whereDate('initiated_at', '>=', $filters['date_from']->toDateString())
                    ->whereDate('initiated_at', '<=', $filters['date_to']->toDateString())
            )
            ->orderByDesc('initiated_at')
            ->get();
    }

    private function summary(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'suggested_total' => round((float) $rows->sum('suggested_amount'), 2),
            'confirmed_total' => round((float) $rows->sum(fn (RentReturn $row) => (float) ($row->confirmed_amount ?? 0)), 2),
            'settled_total' => round((float) $rows->sum(fn (RentReturn $row) => (float) ($row->settlement_amount ?? 0)), 2),
            'pending_count' => $rows->where('status', 'pending_settlement')->count(),
            'posted_count' => $rows->where('ledger_posted', true)->count(),
        ];
    }

    private function propertiesFor(User $user): Collection
    {
        if ($user->hasRole('super_admin')) {
            return Property::query()->orderBy('title', 'asc')->get();
        }

        return $user->managedProperties()->orderBy('title', 'asc')->get();
    }
}
