<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyLedgerEntry;
use App\Models\RentInstalment;
use App\Models\RentLedger;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RentDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();

        $visibleLeases = Lease::query()
            ->visibleTo($user)
            ->with(['tenant', 'unit.property'])
            ->where('status', '!=', 'draft')
            ->get();

        $visibleLeases->each(fn (Lease $lease) => $lease->ensureRentLedgers($user));

        $filters = $request->only(['property_id', 'unit_id']);
        $today = Carbon::today();
        $horizon = $today->copy()->addDays(30);

        $ledgerQuery = RentLedger::query()
            ->visibleTo($user)
            ->with(['lease.tenant', 'lease.unit.property'])
            ->when($request->filled('property_id'), function ($query) use ($request) {
                $query->whereHas('lease.unit', fn ($unitQuery) => $unitQuery->where('property_id', (int) $request->integer('property_id')));
            })
            ->when($request->filled('unit_id'), function ($query) use ($request) {
                $query->whereHas('lease', fn ($leaseQuery) => $leaseQuery->where('unit_id', (int) $request->integer('unit_id')));
            });

        $ledgers = (clone $ledgerQuery)->orderBy('due_on')->get();

        $upcomingDues = $ledgers
            ->filter(fn (RentLedger $ledger) => $ledger->due_on->between($today, $horizon) && (float) $ledger->outstanding_balance > 0)
            ->values();

        $partiallyPaid = $ledgers
            ->where('status', 'partially_paid')
            ->values();

        $overdue = $ledgers
            ->where('status', 'overdue')
            ->values();

        $arrearsTracker = $ledgers
            ->filter(fn (RentLedger $ledger) => (float) $ledger->carried_arrears > 0)
            ->values();

        $recentlyRecorded = RentInstalment::query()
            ->where('voided_at', null)
            ->whereHas('ledger', function ($query) use ($user, $request) {
                $query->visibleTo($user)
                    ->when($request->filled('property_id'), function ($ledgerQuery) use ($request) {
                        $ledgerQuery->whereHas('lease.unit', fn ($unitQuery) => $unitQuery->where('property_id', (int) $request->integer('property_id')));
                    })
                    ->when($request->filled('unit_id'), function ($ledgerQuery) use ($request) {
                        $ledgerQuery->whereHas('lease', fn ($leaseQuery) => $leaseQuery->where('unit_id', (int) $request->integer('unit_id')));
                    });
            })
            ->with(['ledger.lease.tenant', 'ledger.lease.unit.property', 'recorder'])
            ->whereDate('payment_date', '>=', $today->copy()->subDays(7)->toDateString())
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->get();

        $flaggedExpenses = PropertyLedgerEntry::query()
            ->visibleTo($user)
            ->with('property')
            ->where('entry_type', 'expense')
            ->where('status', 'pending_review')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        return view('finance.index', [
            'arrearsTracker' => $arrearsTracker,
            'flaggedExpenses' => $flaggedExpenses,
            'filters' => $filters,
            'overdue' => $overdue,
            'partiallyPaid' => $partiallyPaid,
            'propertyOptions' => $this->propertyOptionsFor($user),
            'recentlyRecorded' => $recentlyRecorded,
            'unitOptions' => $this->unitOptionsFor($user, $request->integer('property_id')),
            'upcomingDues' => $upcomingDues,
            'user' => $user,
        ]);
    }

    private function propertyOptionsFor(User $user)
    {
        if ($user->hasRole('super_admin')) {
            return Property::all()->sortBy('title')->values();
        }

        return $user->managedProperties()->orderBy('title')->get();
    }

    private function unitOptionsFor(User $user, ?int $propertyId)
    {
        return Unit::query()
            ->visibleTo($user)
            ->with('property')
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->orderBy('property_id')
            ->orderBy('unit_number')
            ->get();
    }
}