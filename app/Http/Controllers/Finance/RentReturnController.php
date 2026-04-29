<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\RentReturn;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RentReturnController extends Controller
{
    public function create(Request $request, Lease $lease): View|RedirectResponse
    {
        $this->authorize('update', $lease);

        if ($lease->rentReturn) {
            return to_route('leases.rent-return.show', [$lease, $lease->rentReturn]);
        }

        abort_unless($lease->terminated_at, 404);

        $lease->loadMissing(['tenant', 'unit.property']);
        $lease->ensureRentLedgers($request->user());

        return view('leases.rent-return-create', [
            'draft' => $lease->rentReturnDraft(),
            'lease' => $lease,
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('update', $lease);
        abort_unless($lease->terminated_at, 404);

        /** @var User $user */
        $user = $request->user();

        if ($lease->rentReturn) {
            return to_route('leases.rent-return.show', [$lease, $lease->rentReturn]);
        }

        $lease->loadMissing(['tenant', 'unit.property']);
        $lease->ensureRentLedgers($user);
        $data = $this->validateDraftPayload($request);
        $payload = $this->buildCalculationPayload($lease, $data);

        $rentReturn = $lease->rentReturn()->create([
            ...$payload,
            'property_id' => $lease->unit->property_id,
            'tenant_id' => $lease->tenant_id,
            'unit_id' => $lease->unit_id,
            'status' => 'initiated',
            'notes' => $data['notes'] ?? null,
            'initiated_at' => now(),
            'initiated_by' => $user->id,
            'ledger_posted' => false,
        ]);

        return to_route('leases.rent-return.show', [$lease, $rentReturn])->with('status', 'Rent return initiated.');
    }

    public function show(Request $request, Lease $lease, RentReturn $rentReturn): View
    {
        $this->authorize('view', $lease);
        abort_unless($rentReturn->lease_id === $lease->id, 404);

        $lease->loadMissing(['deposit', 'tenant', 'unit.property']);
        $rentReturn->loadMissing(['initiator', 'processor']);

        return view('leases.rent-return-show', [
            'lease' => $lease,
            'rentReturn' => $rentReturn,
            'settlementMethods' => RentReturn::SETTLEMENT_METHODS,
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, Lease $lease, RentReturn $rentReturn): RedirectResponse
    {
        $this->authorize('update', $lease);
        abort_unless($rentReturn->lease_id === $lease->id, 404);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['confirm', 'settle', 'pending', 'waive'])],
            'vacation_date' => ['required', 'date'],
            'last_paid_through_date' => ['nullable', 'date'],
            'monthly_rent_amount' => ['required', 'numeric', 'min:0'],
            'billing_month_days' => ['required', 'integer', 'between:28,31'],
            'confirmed_amount' => ['required', 'numeric', 'min:0'],
            'override_reason' => ['nullable', 'string'],
            'settlement_method' => ['nullable', 'string', Rule::in(RentReturn::SETTLEMENT_METHODS)],
            'settlement_amount' => ['nullable', 'numeric', 'min:0'],
            'settlement_date' => ['nullable', 'date'],
            'settlement_reference' => ['nullable', 'string', 'max:255'],
            'settlement_details' => ['nullable', 'string'],
            'ledger_posted' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = $this->buildCalculationPayload($lease, $data);
        $confirmedAmount = round((float) $data['confirmed_amount'], 2);

        if (round($confirmedAmount, 2) !== round((float) $payload['suggested_amount'], 2) && blank($data['override_reason'] ?? null)) {
            return back()->withInput()->withErrors([
                'override_reason' => 'Provide an override reason whenever the confirmed amount differs from the suggested amount.',
            ]);
        }

        [$status, $settlementAttributes, $message] = $this->resolveActionState($data, $confirmedAmount);

        $rentReturn->forceFill([
            ...$payload,
            'confirmed_amount' => $confirmedAmount,
            'override_reason' => $data['override_reason'] ?? null,
            'status' => $status,
            'ledger_posted' => $request->boolean('ledger_posted'),
            'notes' => $data['notes'] ?? null,
            'processed_at' => now(),
            'processed_by' => $user->id,
            ...$settlementAttributes,
        ])->save();

        return back()->with('status', $message);
    }

    public function downloadSummary(Request $request, Lease $lease, RentReturn $rentReturn): Response
    {
        $this->authorize('view', $lease);
        abort_unless($rentReturn->lease_id === $lease->id, 404);
        abort_unless($rentReturn->canDownloadSummary(), 403);

        $lease->loadMissing(['deposit', 'tenant', 'unit.property']);
        $rentReturn->loadMissing(['initiator', 'processor']);

        $pdf = Pdf::loadView('leases.rent-return-summary', [
            'lease' => $lease,
            'rentReturn' => $rentReturn,
        ])->setPaper('a4');

        return $pdf->download(sprintf('rent-return-%s.pdf', $lease->lease_number));
    }

    private function validateDraftPayload(Request $request): array
    {
        return $request->validate([
            'vacation_date' => ['required', 'date'],
            'last_paid_through_date' => ['nullable', 'date'],
            'monthly_rent_amount' => ['required', 'numeric', 'min:0'],
            'billing_month_days' => ['required', 'integer', 'between:28,31'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function buildCalculationPayload(Lease $lease, array $data): array
    {
        $vacationDate = Carbon::parse($data['vacation_date'])->startOfDay();
        $lastPaidThroughDate = filled($data['last_paid_through_date'] ?? null)
            ? Carbon::parse($data['last_paid_through_date'])->startOfDay()
            : null;
        $billingMonth = ($lastPaidThroughDate ?: $vacationDate)->copy()->startOfMonth();
        $calculation = RentReturn::calculateSuggestion(
            $vacationDate,
            $lastPaidThroughDate,
            (float) $data['monthly_rent_amount'],
            (int) $data['billing_month_days'],
        );

        return [
            'billing_month' => $billingMonth->toDateString(),
            'daily_rate' => $calculation['daily_rate'],
            'last_paid_through_date' => $lastPaidThroughDate?->toDateString(),
            'suggested_amount' => $calculation['suggested_amount'],
            'unused_days' => $calculation['unused_days'],
            'vacation_date' => $vacationDate->toDateString(),
            'lease_id' => $lease->id,
        ];
    }

    private function resolveActionState(array $data, float $confirmedAmount): array
    {
        return match ($data['action']) {
            'confirm' => ['confirmed', [
                'settlement_method' => null,
                'settlement_amount' => null,
                'settlement_date' => null,
                'settlement_reference' => null,
                'settlement_details' => null,
            ], 'Rent return confirmed.'],
            'pending' => ['pending_settlement', [
                'settlement_method' => 'pending_tbd',
                'settlement_amount' => null,
                'settlement_date' => null,
                'settlement_reference' => null,
                'settlement_details' => $data['settlement_details'] ?? null,
            ], 'Rent return marked as pending settlement.'],
            'waive' => ['waived', [
                'settlement_method' => 'write_off',
                'settlement_amount' => 0,
                'settlement_date' => $data['settlement_date'] ?? now()->toDateString(),
                'settlement_reference' => $data['settlement_reference'] ?? null,
                'settlement_details' => $data['settlement_details'] ?? null,
            ], 'Rent return waived.'],
            default => ['settled', [
                'settlement_method' => $data['settlement_method'] ?? 'cash_refund',
                'settlement_amount' => $data['settlement_amount'] ?? $confirmedAmount,
                'settlement_date' => $data['settlement_date'] ?? now()->toDateString(),
                'settlement_reference' => $data['settlement_reference'] ?? null,
                'settlement_details' => $data['settlement_details'] ?? null,
            ], 'Rent return settled.'],
        };
    }
}