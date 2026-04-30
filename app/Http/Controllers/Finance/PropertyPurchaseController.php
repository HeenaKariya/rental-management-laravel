<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyActivityLog;
use App\Models\PropertyLedgerEntry;
use App\Models\PropertyLoan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyPurchaseController extends Controller
{
    public function show(Request $request, Property $property): View
    {
        $this->authorize('view', $property);

        $property->loadMissing([
            'loan.emiLogs.recorder',
            'purchase',
        ]);

        return view('finance.purchase', [
            'loanSummary' => $property->loan?->summary(),
            'property' => $property,
            'user' => $request->user(),
        ]);
    }

    public function storePurchase(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'stamp_duty' => ['nullable', 'numeric', 'min:0'],
            'registration_charges' => ['nullable', 'numeric', 'min:0'],
            'other_acquisition_costs' => ['nullable', 'numeric', 'min:0'],
            'seller_name' => ['nullable', 'string', 'max:255'],
            'seller_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $totalAcquisitionCost = round((float) $data['purchase_price']
            + (float) ($data['stamp_duty'] ?? 0)
            + (float) ($data['registration_charges'] ?? 0)
            + (float) ($data['other_acquisition_costs'] ?? 0), 2);

        $property->purchase()->updateOrCreate(
            ['property_id' => $property->id],
            [
                'purchase_price' => $data['purchase_price'],
                'purchase_date' => $data['purchase_date'] ?? null,
                'stamp_duty' => $data['stamp_duty'] ?? 0,
                'registration_charges' => $data['registration_charges'] ?? 0,
                'other_acquisition_costs' => $data['other_acquisition_costs'] ?? 0,
                'total_acquisition_cost' => $totalAcquisitionCost,
                'seller_name' => $data['seller_name'] ?? null,
                'seller_contact' => $data['seller_contact'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        PropertyActivityLog::record($property, 'property.purchase_updated', $user, [
            'total_acquisition_cost' => $totalAcquisitionCost,
        ]);

        return back()->with('status', 'Purchase details saved.');
    }

    public function storeLoan(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'lender_name' => ['required', 'string', 'max:255'],
            'loan_amount' => ['required', 'numeric', 'min:0'],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'interest_rate_type' => ['required', 'string', Rule::in(['fixed', 'floating'])],
            'loan_start_date' => ['required', 'date'],
            'tenure_months' => ['required', 'integer', 'min:1'],
            'emi_amount' => ['required', 'numeric', 'min:0.01'],
            'emi_due_day' => ['required', 'integer', 'between:1,28'],
            'notes' => ['nullable', 'string'],
        ]);

        $property->loan()->updateOrCreate(
            ['property_id' => $property->id],
            [
                ...$data,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        PropertyActivityLog::record($property, 'property.loan_updated', $user, [
            'lender' => $data['lender_name'],
            'loan_amount' => (float) $data['loan_amount'],
        ]);

        return back()->with('status', 'Loan details saved.');
    }

    public function storeEmi(Request $request, Property $property, PropertyLoan $loan): RedirectResponse
    {
        $this->authorize('update', $property);
        abort_unless($loan->property_id === $property->id, 404);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'date_paid' => ['required', 'date'],
            'principal_component' => ['required', 'numeric', 'min:0'],
            'interest_component' => ['required', 'numeric', 'min:0'],
            'outstanding_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $nextEmiNumber = ((int) $loan->emiLogs()->max('emi_number')) + 1;

        $emiLog = $loan->emiLogs()->create([
            'emi_number' => $nextEmiNumber,
            'amount_paid' => $data['amount_paid'],
            'date_paid' => $data['date_paid'],
            'principal_component' => $data['principal_component'],
            'interest_component' => $data['interest_component'],
            'outstanding_balance' => $data['outstanding_balance'],
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $user->id,
        ]);

        $flaggedReason = PropertyLedgerEntry::shouldFlagExpense((float) $data['amount_paid'], 'loan_emi');

        $property->ledgerEntries()->firstOrCreate(
            [
                'source_type' => $emiLog::class,
                'source_id' => $emiLog->id,
                'entry_type' => 'expense',
                'category' => 'loan_emi',
            ],
            [
                'entry_date' => $data['date_paid'],
                'amount' => $data['amount_paid'],
                'vendor_name' => $loan->lender_name,
                'notes' => 'Auto posted from EMI #'.$nextEmiNumber,
                'status' => $flaggedReason ? 'pending_review' : 'approved',
                'flagged_reason' => $flaggedReason,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        PropertyActivityLog::record($property, 'property.loan_emi_recorded', $user, [
            'amount_paid' => (float) $data['amount_paid'],
            'emi_number' => $nextEmiNumber,
        ]);

        return back()->with('status', 'EMI payment logged and ledger expense posted.');
    }
}