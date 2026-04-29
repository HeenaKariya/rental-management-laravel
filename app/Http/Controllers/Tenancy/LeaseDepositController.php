<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\LeaseDepositEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class LeaseDepositController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', LeaseDeposit::class);

        /** @var User $user */
        $user = $request->user();

        $deposits = LeaseDeposit::query()
            ->visibleTo($user)
            ->with(['lease.tenant', 'lease.unit.property'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->get();

        return view('deposits.index', [
            'deposits' => $deposits,
            'filters' => $request->only(['status']),
            'leaseOptions' => $this->leaseOptionsFor($user),
            'statusOptions' => LeaseDeposit::STATUSES,
            'user' => $user,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LeaseDeposit::class);

        return view('deposits.create', [
            'deposit' => new LeaseDeposit(),
            'leaseOptions' => $this->leaseOptionsFor($request->user()),
            'statusOptions' => LeaseDeposit::STATUSES,
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaseDeposit::class);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'lease_id' => ['required', 'integer', 'exists:leases,id'],
            'expected_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'initial_collection' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lease = $this->findVisibleLeaseOrFail($user, (int) $data['lease_id']);

        $deposit = LeaseDeposit::query()->firstOrCreate(
            ['lease_id' => $lease->id],
            [
                'expected_amount' => $data['expected_amount'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        if (! empty($data['initial_collection']) && (float) $data['initial_collection'] > 0 && $deposit->entries()->doesntExist()) {
            $deposit->postEntry('collection', (float) $data['initial_collection'], $user, 'Initial security deposit collection.');
        }

        return to_route('deposits.show', $deposit)->with('status', 'Deposit account created.');
    }

    public function show(Request $request, LeaseDeposit $deposit): View
    {
        $this->authorize('view', $deposit);

        $deposit->load(['entries.creator', 'lease.tenant', 'lease.unit.property']);

        return view('deposits.show', [
            'deposit' => $deposit,
            'entryTypeOptions' => LeaseDepositEntry::ENTRY_TYPES,
            'user' => $request->user(),
        ]);
    }

    public function postEntry(Request $request, LeaseDeposit $deposit): RedirectResponse
    {
        $this->authorize('postEntry', $deposit);

        $data = $request->validate([
            'entry_type' => ['required', 'string', Rule::in(LeaseDepositEntry::ENTRY_TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $deposit->postEntry($data['entry_type'], (float) $data['amount'], $request->user(), $data['notes'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('status', 'Deposit entry posted.');
    }

    private function leaseOptionsFor(?User $user)
    {
        if (! $user instanceof User) {
            return collect();
        }

        return Lease::query()
            ->visibleTo($user)
            ->with(['tenant', 'unit.property'])
            ->get()
            ->sortBy(fn (Lease $lease) => $lease->lease_number)
            ->values();
    }

    private function findVisibleLeaseOrFail(User $user, int $leaseId): Lease
    {
        $lease = Lease::query()->visibleTo($user)->find($leaseId);

        abort_unless($lease, 403);

        return $lease;
    }
}