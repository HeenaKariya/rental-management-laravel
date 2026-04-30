<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use App\Models\RentLedger;
use App\Models\Unit;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArrearsReportController extends Controller
{
    private const DEFAULT_ALERT_THRESHOLD_MONTHS = 2;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        return view('finance.arrears-report', [
            'filters' => $filters,
            'properties' => $this->propertiesFor($user),
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'unitOptions' => $this->unitOptionsFor($user, $filters['property_id']),
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
            fputcsv($handle, ['Report', 'Arrears and Partial Payments']);
            fputcsv($handle, ['Period', $filters['range_label']]);
            fputcsv($handle, ['Status', $filters['status'] === 'all' ? 'All statuses' : str($filters['status'])->replace('_', ' ')->title()->toString()]);
            fputcsv($handle, ['Alert threshold (months)', $filters['alert_threshold_months']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Property', 'Unit', 'Lease', 'Tenant', 'Payment month', 'Due on', 'Status', 'Carried arrears', 'Outstanding', 'Instalments', 'Instalments paid', 'Last payment date', 'Arrears alert']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->lease?->unit?->property?->title,
                    $row->lease?->unit?->unit_number,
                    $row->lease?->lease_number,
                    $row->lease?->tenant?->full_name,
                    $row->payment_month?->toDateString(),
                    $row->due_on?->toDateString(),
                    str($row->status)->replace('_', ' ')->title()->toString(),
                    (float) $row->carried_arrears,
                    (float) $row->outstanding_balance,
                    (int) ($row->instalments_count ?? 0),
                    (float) ($row->instalments_paid_total ?? 0),
                    $row->last_payment_date,
                    $row->arrears_alert ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, 'arrears-report.csv', [
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

        $pdf = Pdf::loadView('finance.arrears-report-pdf', [
            'filters' => $filters,
            'generatedAt' => now(),
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('arrears-report.pdf');
    }

    private function normalizeFilters(Request $request): array
    {
        $payload = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'status' => ['nullable', 'string', 'in:all,unpaid,partially_paid,overdue'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'alert_threshold_months' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $from = isset($payload['date_from']) ? Carbon::parse($payload['date_from'])->startOfDay() : null;
        $to = isset($payload['date_to']) ? Carbon::parse($payload['date_to'])->endOfDay() : null;

        return [
            'property_id' => isset($payload['property_id']) ? (int) $payload['property_id'] : null,
            'unit_id' => isset($payload['unit_id']) ? (int) $payload['unit_id'] : null,
            'status' => $payload['status'] ?? 'all',
            'date_from' => $from,
            'date_to' => $to,
            'alert_threshold_months' => isset($payload['alert_threshold_months'])
                ? (int) $payload['alert_threshold_months']
                : self::DEFAULT_ALERT_THRESHOLD_MONTHS,
            'range_label' => $from && $to
                ? sprintf('%s to %s', $from->toDateString(), $to->toDateString())
                : 'All time',
        ];
    }

    private function queryRows(User $user, array $filters): Collection
    {
        $rows = RentLedger::query()
            ->visibleTo($user)
            ->with([
                'lease.tenant',
                'lease.unit.property',
            ])
            ->withCount('activeInstalments as instalments_count')
            ->withSum('activeInstalments as instalments_paid_total', 'amount_paid')
            ->withMax('activeInstalments as last_payment_date', 'payment_date')
            ->where(function ($query) {
                $query->where('carried_arrears', '>', 0)
                    ->orWhere('outstanding_balance', '>', 0);
            })
            ->when(
                $filters['property_id'],
                fn ($query) => $query->whereHas('lease.unit', fn ($unitQuery) => $unitQuery->where('property_id', $filters['property_id']))
            )
            ->when(
                $filters['unit_id'],
                fn ($query) => $query->whereHas('lease', fn ($leaseQuery) => $leaseQuery->where('unit_id', $filters['unit_id']))
            )
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                $filters['date_from'] && $filters['date_to'],
                fn ($query) => $query
                    ->whereDate('payment_month', '>=', $filters['date_from']->toDateString())
                    ->whereDate('payment_month', '<=', $filters['date_to']->toDateString())
            )
            ->orderByDesc('payment_month')
            ->orderByDesc('id')
            ->get();

        return $this->attachArrearsAlert($rows, $filters['alert_threshold_months']);
    }

    private function attachArrearsAlert(Collection $rows, int $thresholdMonths): Collection
    {
        $leaseIds = $rows
            ->pluck('lease_id')
            ->filter()
            ->unique()
            ->values();

        if ($leaseIds->isEmpty()) {
            return $rows->map(function (RentLedger $row): RentLedger {
                $row->setAttribute('arrears_alert', false);
                $row->setAttribute('max_arrears_streak', 0);

                return $row;
            });
        }

        $leaseIdsArray = $leaseIds->all();

        $historyByLease = RentLedger::query()
            ->where(function ($query) use ($leaseIdsArray): void {
                foreach ($leaseIdsArray as $index => $leaseId) {
                    if ($index === 0) {
                        $query->where('lease_id', $leaseId);

                        continue;
                    }

                    $query->orWhere('lease_id', $leaseId);
                }
            })
            ->orderBy('payment_month')
            ->get(['lease_id', 'payment_month', 'carried_arrears'])
            ->groupBy('lease_id');

        $maxConsecutiveByLease = $historyByLease
            ->map(function (Collection $leaseRows): int {
                $streak = 0;
                $maxStreak = 0;

                $leaseRows
                    ->each(function (RentLedger $row) use (&$streak, &$maxStreak): void {
                        if ((float) $row->carried_arrears > 0) {
                            $streak++;
                            $maxStreak = max($maxStreak, $streak);

                            return;
                        }

                        $streak = 0;
                    });

                return $maxStreak;
            });

        return $rows->map(function (RentLedger $row) use ($maxConsecutiveByLease, $thresholdMonths): RentLedger {
            $maxStreak = (int) ($maxConsecutiveByLease[$row->lease_id] ?? 0);
            $row->setAttribute('arrears_alert', $maxStreak > $thresholdMonths);
            $row->setAttribute('max_arrears_streak', $maxStreak);

            return $row;
        });
    }

    private function summary(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'outstanding_total' => round((float) $rows->sum('outstanding_balance'), 2),
            'carried_arrears_total' => round((float) $rows->sum('carried_arrears'), 2),
            'partial_count' => $rows->where('status', 'partially_paid')->count(),
            'overdue_count' => $rows->where('status', 'overdue')->count(),
            'alerted_count' => $rows->where('arrears_alert', true)->count(),
        ];
    }

    private function propertiesFor(User $user): Collection
    {
        if ($user->hasRole('super_admin')) {
            return Property::query()->orderBy('title', 'asc')->get();
        }

        return $user->managedProperties()->orderBy('title', 'asc')->get();
    }

    private function unitOptionsFor(User $user, ?int $propertyId): Collection
    {
        return Unit::query()
            ->visibleTo($user)
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->orderBy('unit_number', 'asc')
            ->get();
    }
}
