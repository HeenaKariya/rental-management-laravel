<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\RentInstalment;
use App\Models\RentLedger;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class LeasePaymentHistoryController extends Controller
{
    public function show(Request $request, Lease $lease): View
    {
        $this->authorize('view', $lease);

        $lease->loadMissing(['tenant', 'unit.property']);
        $lease->ensureRentLedgers($request->user());
        $lease->load([
            'rentLedgers.instalments.recorder',
            'rentLedgers.instalments.voider',
            'rentLedgers.instalments.corrections.changer',
        ]);

        return view('leases.payments', [
            'lease' => $lease,
            'paymentModes' => RentInstalment::PAYMENT_MODES,
            'user' => $request->user(),
        ]);
    }

    public function storeInstalment(Request $request, Lease $lease, RentLedger $ledger): RedirectResponse
    {
        $this->authorize('update', $lease);
        abort_unless($ledger->lease_id === $lease->id, 404);

        /** @var User $user */
        $user = $request->user();
        $lease->ensureRentLedgers($user);
        $ledger->refresh();

        $data = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_mode' => ['required', 'string', Rule::in(RentInstalment::PAYMENT_MODES)],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'late_fee_charged' => ['nullable', 'numeric', 'min:0'],
            'late_fee_waiver_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentDate = Carbon::parse($data['payment_date']);
        $suggestedLateFee = $ledger->suggestedLateFeeForDate($paymentDate);
        $lateFeeCharged = array_key_exists('late_fee_charged', $data) && $data['late_fee_charged'] !== null
            ? (float) $data['late_fee_charged']
            : $suggestedLateFee;

        if ($suggestedLateFee > 0 && round($lateFeeCharged, 2) !== round($suggestedLateFee, 2) && blank($data['late_fee_waiver_reason'] ?? null)) {
            return back()->withInput()->withErrors([
                'late_fee_waiver_reason' => 'Provide a waiver reason whenever the suggested late fee is overridden or waived.',
            ]);
        }

        $ledger->recordInstalment([
            'amount_paid' => $data['amount_paid'],
            'late_fee_charged' => $lateFeeCharged,
            'payment_date' => $paymentDate->toDateString(),
            'payment_mode' => $data['payment_mode'],
            'reference_number' => $data['reference_number'] ?? null,
            'late_fee_waiver_reason' => $data['late_fee_waiver_reason'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $user);

        return back()->with('status', 'Instalment recorded.');
    }

    public function correctInstalment(Request $request, Lease $lease, RentLedger $ledger, RentInstalment $instalment): RedirectResponse
    {
        $this->authorize('update', $lease);
        abort_unless($ledger->lease_id === $lease->id, 404);
        abort_unless($instalment->rent_ledger_id === $ledger->id, 404);

        $data = $request->validate([
            'payment_mode' => ['required', 'string', Rule::in(RentInstalment::PAYMENT_MODES)],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $instalment->correctMetadata([
                'payment_mode' => $data['payment_mode'],
                'reference_number' => $data['reference_number'] ?? null,
            ], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payment_mode' => $exception->getMessage()]);
        }

        return back()->with('status', 'Instalment metadata corrected.');
    }

    public function voidInstalment(Request $request, Lease $lease, RentLedger $ledger, RentInstalment $instalment): RedirectResponse
    {
        $this->authorize('update', $lease);
        abort_unless($ledger->lease_id === $lease->id, 404);
        abort_unless($instalment->rent_ledger_id === $ledger->id, 404);

        $data = $request->validate([
            'void_reason' => ['required', 'string'],
        ]);

        try {
            $instalment->void($data['void_reason'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['void_reason' => $exception->getMessage()]);
        }

        return back()->with('status', 'Instalment voided and ledger recalculated.');
    }

    public function downloadReceipt(Request $request, Lease $lease, RentLedger $ledger, RentInstalment $instalment): Response
    {
        $this->authorize('view', $lease);
        abort_unless($ledger->lease_id === $lease->id, 404);
        abort_unless($instalment->rent_ledger_id === $ledger->id, 404);

        $lease->loadMissing(['tenant', 'unit.property']);
        $ledger->loadMissing(['instalments.recorder']);

        $orderedInstalments = $ledger->instalments->sortBy('instalment_number')->values();
        $cumulativeAmountPaid = (float) $orderedInstalments
            ->where('instalment_number', '<=', $instalment->instalment_number)
            ->sum('amount_paid');
        $remainingOutstanding = max(((float) $ledger->total_due + (float) $ledger->late_fee_total) - $cumulativeAmountPaid, 0);

        $pdf = Pdf::loadView('leases.receipt', [
            'cumulativeAmountPaid' => $cumulativeAmountPaid,
            'instalment' => $instalment,
            'lease' => $lease,
            'ledger' => $ledger,
            'remainingOutstanding' => $remainingOutstanding,
        ])->setPaper('a4');

        return $pdf->download(sprintf(
            'rent-receipt-%s-%s.pdf',
            $lease->lease_number,
            $instalment->instalment_number
        ));
    }
}