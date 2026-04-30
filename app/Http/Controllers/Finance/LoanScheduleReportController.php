<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyLoanEmiLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanScheduleReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $this->normalizeFilters($request);
        $rows = $this->queryRows($user, $filters);

        return view('finance.loan-schedule-report', [
            'filters' => $filters,
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
            fputcsv($handle, ['Report', 'Loan Schedule']);
            fputcsv($handle, ['Period', $filters['range_label']]);
            fputcsv($handle, ['Property scope', $filters['property_id'] ? 'Single property' : 'All visible properties']);
            fputcsv($handle, []);
            fputcsv($handle, ['Property', 'Lender', 'EMI #', 'Date paid', 'Amount paid', 'Principal', 'Interest', 'Outstanding balance']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->loan?->property?->title,
                    $row->loan?->lender_name,
                    (int) $row->emi_number,
                    $row->date_paid?->toDateString(),
                    (float) $row->amount_paid,
                    (float) $row->principal_component,
                    (float) $row->interest_component,
                    (float) $row->outstanding_balance,
                ]);
            }

            fclose($handle);
        }, 'loan-schedule-report.csv', [
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

        $pdf = Pdf::loadView('finance.loan-schedule-report-pdf', [
            'filters' => $filters,
            'generatedAt' => now(),
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('loan-schedule-report.pdf');
    }

    private function normalizeFilters(Request $request): array
    {
        $payload = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($payload['date_from']) ? Carbon::parse($payload['date_from'])->startOfDay() : null;
        $to = isset($payload['date_to']) ? Carbon::parse($payload['date_to'])->endOfDay() : null;

        return [
            'property_id' => isset($payload['property_id']) ? (int) $payload['property_id'] : null,
            'date_from' => $from,
            'date_to' => $to,
            'range_label' => $from && $to
                ? sprintf('%s to %s', $from->toDateString(), $to->toDateString())
                : 'All time',
        ];
    }

    private function queryRows(User $user, array $filters): Collection
    {
        return PropertyLoanEmiLog::query()
            ->with(['loan.property'])
            ->whereHas('loan.property', fn ($query) => $query->visibleTo($user))
            ->when(
                $filters['property_id'],
                fn ($query) => $query->whereHas('loan', fn ($loanQuery) => $loanQuery->where('property_id', $filters['property_id']))
            )
            ->when(
                $filters['date_from'] && $filters['date_to'],
                fn ($query) => $query
                    ->whereDate('date_paid', '>=', $filters['date_from']->toDateString())
                    ->whereDate('date_paid', '<=', $filters['date_to']->toDateString())
            )
            ->orderByDesc('date_paid')
            ->orderByDesc('id')
            ->get();
    }

    private function propertiesFor(User $user): Collection
    {
        if ($user->hasRole('super_admin')) {
            return Property::query()->orderBy('title', 'asc')->get();
        }

        return $user->managedProperties()->orderBy('title', 'asc')->get();
    }

    private function summary(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'amount_paid_total' => round((float) $rows->sum('amount_paid'), 2),
            'principal_total' => round((float) $rows->sum('principal_component'), 2),
            'interest_total' => round((float) $rows->sum('interest_component'), 2),
        ];
    }
}
