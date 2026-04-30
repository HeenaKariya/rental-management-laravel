<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyLedgerEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyLedgerController extends Controller
{
    public function index(Request $request, Property $property): View
    {
        $this->authorize('view', $property);

        $entries = $property->ledgerEntries()
            ->with(['creator', 'reviewer'])
            ->when($request->filled('entry_type'), fn ($query) => $query->where('entry_type', $request->string('entry_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('entry_date')
            ->latest('id')
            ->get();

        return view('finance.ledger', [
            'canManage' => $request->user()?->hasAnyRole(['super_admin', 'manager']) === true,
            'entries' => $entries,
            'expenseCategories' => PropertyLedgerEntry::EXPENSE_CATEGORIES,
            'filters' => $request->only(['entry_type', 'status']),
            'property' => $property,
            'user' => $request->user(),
        ]);
    }

    public function storeIncome(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $property->ledgerEntries()->create([
            'entry_type' => 'income',
            'entry_date' => $data['entry_date'],
            'category' => str($data['category'])->slug('_')->toString(),
            'amount' => $data['amount'],
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'approved',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return back()->with('status', 'Income entry recorded.');
    }

    public function storeExpense(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'category' => ['required', 'string', Rule::in(PropertyLedgerEntry::EXPENSE_CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $flaggedReason = PropertyLedgerEntry::shouldFlagExpense((float) $data['amount'], $data['category']);
        $status = $flaggedReason ? 'pending_review' : 'approved';

        $property->ledgerEntries()->create([
            'entry_type' => 'expense',
            'entry_date' => $data['entry_date'],
            'category' => $data['category'],
            'amount' => $data['amount'],
            'vendor_name' => $data['vendor_name'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $status,
            'flagged_reason' => $flaggedReason,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $message = $status === 'pending_review'
            ? 'Expense recorded and sent to Super Admin review queue.'
            : 'Expense entry recorded.';

        return back()->with('status', $message);
    }

    public function reviewExpense(Request $request, Property $property, PropertyLedgerEntry $entry): RedirectResponse
    {
        $this->authorize('archive', $property);
        abort_unless($entry->property_id === $property->id, 404);
        abort_unless($entry->entry_type === 'expense', 422);

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'review_notes' => ['nullable', 'string'],
        ]);

        $entry->forceFill([
            'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ])->save();

        return back()->with('status', 'Expense review decision saved.');
    }
}