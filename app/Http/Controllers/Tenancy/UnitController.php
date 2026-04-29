<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        /** @var User $user */
        $user = $request->user();

        $units = Unit::query()
            ->visibleTo($user)
            ->with('property')
            ->when($request->filled('property_id'), fn ($query) => $query->where('property_id', (int) $request->integer('property_id')))
            ->when($request->filled('occupancy_status'), fn ($query) => $query->where('occupancy_status', $request->string('occupancy_status')->toString()))
            ->orderBy('property_id')
            ->orderBy('unit_number')
            ->get();

        return view('units.index', [
            'filters' => $request->only(['property_id', 'occupancy_status']),
            'occupancyOptions' => Unit::OCCUPANCY_STATUSES,
            'propertyOptions' => $this->propertyOptionsFor($user),
            'units' => $units,
            'user' => $user,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Unit::class);

        return view('units.create', [
            'occupancyOptions' => Unit::OCCUPANCY_STATUSES,
            'propertyOptions' => $this->propertyOptionsFor($request->user()),
            'unit' => new Unit(),
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Unit::class);

        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPayload($request);

        $property = $this->findVisiblePropertyOrFail($user, (int) $data['property_id']);

        $unit = Unit::query()->create([
            ...$data,
            'property_id' => $property->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return to_route('units.show', $unit)->with('status', 'Unit created.');
    }

    public function show(Request $request, Unit $unit): View
    {
        $this->authorize('view', $unit);

        $unit->load('property');

        return view('units.show', [
            'unit' => $unit,
            'user' => $request->user(),
        ]);
    }

    public function edit(Request $request, Unit $unit): View
    {
        $this->authorize('update', $unit);

        $unit->load('property');

        return view('units.edit', [
            'occupancyOptions' => Unit::OCCUPANCY_STATUSES,
            'propertyOptions' => $this->propertyOptionsFor($request->user()),
            'unit' => $unit,
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPayload($request, $unit);

        $property = $this->findVisiblePropertyOrFail($user, (int) $data['property_id']);

        $unit->fill([
            ...$data,
            'property_id' => $property->id,
            'updated_by' => $user->id,
        ])->save();

        return to_route('units.show', $unit)->with('status', 'Unit updated.');
    }

    private function validatedPayload(Request $request, ?Unit $unit = null): array
    {
        $propertyId = (int) $request->integer('property_id');

        return $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'unit_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('units', 'unit_number')
                    ->where(fn ($query) => $query->where('property_id', $propertyId))
                    ->ignore($unit?->id),
            ],
            'floor' => ['nullable', 'string', 'max:40'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'area_unit' => ['nullable', 'string', Rule::in(['sqft', 'sqm'])],
            'occupancy_status' => ['required', 'string', Rule::in(Unit::OCCUPANCY_STATUSES)],
            'vacant_since' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function propertyOptionsFor(?User $user)
    {
        if (! $user instanceof User) {
            return collect();
        }

        return Property::query()
            ->visibleTo($user)
            ->get()
            ->sortBy('title')
            ->values();
    }

    private function findVisiblePropertyOrFail(User $user, int $propertyId): Property
    {
        $property = Property::query()->visibleTo($user)->find($propertyId);

        abort_unless($property, 403);

        return $property;
    }
}