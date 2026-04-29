<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lease::class);

        /** @var User $user */
        $user = $request->user();

        $leases = Lease::query()
            ->visibleTo($user)
            ->with(['tenant', 'unit.property'])
            ->when($request->filled('unit_id'), fn ($query) => $query->where('unit_id', (int) $request->integer('unit_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('start_on')
            ->get();

        return view('leases.index', [
            'filters' => $request->only(['unit_id', 'status']),
            'leaseUnits' => $this->unitOptionsFor($user),
            'leases' => $leases,
            'statusOptions' => Lease::STATUSES,
            'user' => $user,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Lease::class);

        return view('leases.create', [
            'lease' => new Lease(),
            'leaseUnits' => $this->unitOptionsFor($request->user()),
            'statusOptions' => Lease::STATUSES,
            'tenantOptions' => collect(),
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Lease::class);

        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPayload($request);

        $unit = $this->findVisibleUnitOrFail($user, (int) $data['unit_id']);
        $tenant = $this->findVisibleTenantOrFail($user, (int) $data['tenant_id'], $unit->id);

        $lease = new Lease([
            ...$data,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        if ($lease->isActive() && $lease->hasOverlappingActiveLease()) {
            return back()->withInput()->withErrors(['unit_id' => 'This unit already has an active lease.']);
        }

        try {
            $lease->save();
        } catch (QueryException) {
            return back()->withInput()->withErrors(['unit_id' => 'This unit already has an active lease.']);
        }

        return to_route('leases.show', $lease)->with('status', 'Lease created.');
    }

    public function show(Request $request, Lease $lease): View
    {
        $this->authorize('view', $lease);

        $lease->load(['previousLease', 'renewals', 'tenant', 'unit.property']);

        return view('leases.show', [
            'lease' => $lease,
            'user' => $request->user(),
        ]);
    }

    public function edit(Request $request, Lease $lease): View
    {
        $this->authorize('update', $lease);

        $lease->load(['tenant', 'unit.property']);

        return view('leases.edit', [
            'lease' => $lease,
            'leaseUnits' => $this->unitOptionsFor($request->user()),
            'statusOptions' => Lease::STATUSES,
            'tenantOptions' => $this->tenantOptionsFor($request->user()),
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('update', $lease);

        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPayload($request, $lease);

        $unit = $this->findVisibleUnitOrFail($user, (int) $data['unit_id']);
        $tenant = $this->findVisibleTenantOrFail($user, (int) $data['tenant_id'], $unit->id);

        $lease->fill([
            ...$data,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'updated_by' => $user->id,
        ]);

        if ($lease->isActive() && $lease->hasOverlappingActiveLease()) {
            return back()->withInput()->withErrors(['unit_id' => 'This unit already has an active lease.']);
        }

        try {
            $lease->save();
        } catch (QueryException) {
            return back()->withInput()->withErrors(['unit_id' => 'This unit already has an active lease.']);
        }

        return to_route('leases.show', $lease)->with('status', 'Lease updated.');
    }

    public function renew(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('renew', $lease);

        if (! $lease->isActive()) {
            return back()->withErrors(['start_on' => 'Only active leases can be renewed.']);
        }

        $data = $request->validate([
            'start_on' => ['required', 'date', 'after:'.$lease->end_on->format('Y-m-d')],
            'end_on' => ['required', 'date', 'after:start_on'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:31'],
            'late_fee_mode' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
            'late_fee_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($lease, $data, $user): void {
            $lease->forceFill([
                'status' => 'renewed',
                'updated_by' => $user->id,
            ])->save();

            Lease::query()->create([
                'unit_id' => $lease->unit_id,
                'tenant_id' => $lease->tenant_id,
                'previous_lease_id' => $lease->id,
                'start_on' => $data['start_on'],
                'end_on' => $data['end_on'],
                'rent_amount' => $data['rent_amount'],
                'billing_day' => $data['billing_day'],
                'grace_period_days' => $data['grace_period_days'] ?? $lease->grace_period_days,
                'late_fee_mode' => $data['late_fee_mode'] ?? $lease->late_fee_mode,
                'late_fee_value' => $data['late_fee_value'] ?? $lease->late_fee_value,
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        return to_route('leases.index')->with('status', 'Lease renewed.');
    }

    private function validatedPayload(Request $request, ?Lease $lease = null): array
    {
        return $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'start_on' => ['required', 'date'],
            'end_on' => ['required', 'date', 'after:start_on'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:31'],
            'late_fee_mode' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
            'late_fee_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(Lease::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function unitOptionsFor(?User $user)
    {
        if (! $user instanceof User) {
            return collect();
        }

        return Unit::query()
            ->visibleTo($user)
            ->with('property')
            ->get()
            ->sortBy(fn (Unit $unit) => $unit->property->title.'-'.$unit->unit_number)
            ->values();
    }

    private function tenantOptionsFor(?User $user)
    {
        if (! $user instanceof User) {
            return collect();
        }

        return Tenant::query()
            ->visibleTo($user)
            ->with('unit.property')
            ->get()
            ->sortBy(fn (Tenant $tenant) => $tenant->full_name)
            ->values();
    }

    private function findVisibleUnitOrFail(User $user, int $unitId): Unit
    {
        $unit = Unit::query()->visibleTo($user)->find($unitId);

        abort_unless($unit, 403);

        return $unit;
    }

    private function findVisibleTenantOrFail(User $user, int $tenantId, int $unitId): Tenant
    {
        $tenant = Tenant::query()
            ->visibleTo($user)
            ->where('unit_id', $unitId)
            ->find($tenantId);

        abort_unless($tenant, 403);

        return $tenant;
    }
}