<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Property::class);

        /** @var User $user */
        $user = $request->user();

        $properties = Property::query()
            ->visibleTo($user)
            ->with(['managers'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('lifecycle_stage'), fn ($query) => $query->where('lifecycle_stage', $request->string('lifecycle_stage')->toString()))
            ->when(
                $user->hasRole('super_admin') && $request->filled('assigned_manager_id'),
                fn ($query) => $query->whereHas('activeManagerAssignments', fn ($assignmentQuery) => $assignmentQuery->where('manager_id', (int) $request->integer('assigned_manager_id')))
            )
            ->latest()
            ->get();

        return view('properties.index', [
            'filters' => $request->only(['type', 'lifecycle_stage', 'assigned_manager_id']),
            'managerOptions' => $user->hasRole('super_admin') ? $this->managerOptions() : collect(),
            'properties' => $properties,
            'propertyTypes' => Property::TYPES,
            'stageOptions' => Property::LIFECYCLE_STAGES,
            'user' => $user,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Property::class);

        return view('properties.create', [
            'managerOptions' => $this->managerOptions(),
            'property' => new Property(),
            'propertyTypes' => Property::TYPES,
            'stageOptions' => Property::LIFECYCLE_STAGES,
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Property::class);

        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPayload($request);

        $property = Property::query()->create([
            ...$data,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        PropertyActivityLog::record($property, 'property.created', $user);

        if ($user->hasRole('manager') && ! $user->hasRole('super_admin')) {
            $property->assignManager($user, $user);
        } elseif ($request->filled('assigned_manager_id')) {
            $manager = $this->findManagerOrFail((int) $request->integer('assigned_manager_id'));
            $property->assignManager($manager, $user);
        }

        $this->storeUploadedPhotos($request, $property, $user);

        return to_route('properties.show', $property)->with('status', 'Property created.');
    }

    public function show(Request $request, Property $property): View
    {
        $this->authorize('view', $property);

        $property->load([
            'activeManagerAssignments.manager',
            'activityLogs.actor',
            'activityLogs.subjectUser',
            'managers.roles',
            'photos',
        ]);

        return view('properties.show', [
            'managerOptions' => $this->managerOptions(),
            'property' => $property,
            'user' => $request->user(),
        ]);
    }

    public function edit(Request $request, Property $property): View
    {
        $this->authorize('update', $property);

        $property->load(['photos', 'managers']);

        return view('properties.edit', [
            'managerOptions' => $this->managerOptions(),
            'property' => $property,
            'propertyTypes' => Property::TYPES,
            'stageOptions' => Property::LIFECYCLE_STAGES,
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        /** @var User $user */
        $user = $request->user();

        if (! $user->hasRole('super_admin') && $request->input('lifecycle_stage') !== $property->lifecycle_stage) {
            abort(403);
        }

        $data = $this->validatedPayload($request, $property);
        $previousLifecycleStage = $property->lifecycle_stage;

        $property->fill([
            ...$data,
            'updated_by' => $user->id,
        ])->save();

        PropertyActivityLog::record($property, 'property.updated', $user);

        if ($previousLifecycleStage !== $property->lifecycle_stage) {
            PropertyActivityLog::record($property, 'property.lifecycle_changed', $user, [
                'from' => $previousLifecycleStage,
                'to' => $property->lifecycle_stage,
            ]);
        }

        if ($user->hasRole('super_admin')) {
            $selectedManager = $request->filled('assigned_manager_id')
                ? $this->findManagerOrFail((int) $request->integer('assigned_manager_id'))
                : null;

            $property->syncAssignedManager($selectedManager, $user);
        }

        $this->storeUploadedPhotos($request, $property, $user);

        if ($request->filled('photo_orders')) {
            $property->reorderPhotos((array) $request->input('photo_orders'));
        }

        if ($request->filled('cover_photo_id')) {
            $property->refreshCoverPhoto((int) $request->integer('cover_photo_id'));
        }

        return to_route('properties.show', $property)->with('status', 'Property updated.');
    }

    public function archive(Request $request, Property $property): RedirectResponse
    {
        $this->authorize('archive', $property);

        $property->forceFill([
            'deleted_at' => now(),
        ])->save();

        PropertyActivityLog::record($property, 'property.archived', $request->user());

        return to_route('properties.index')->with('status', 'Property archived.');
    }

    private function validatedPayload(Request $request, ?Property $property = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Property::TYPES)],
            'street_address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'area_unit' => ['nullable', 'string', Rule::in(['sqft', 'sqm'])],
            'lifecycle_stage' => ['required', 'string', Rule::in(Property::LIFECYCLE_STAGES)],
            'description' => ['nullable', 'string'],
            'assigned_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'cover_photo_id' => ['nullable', 'integer', Rule::exists('property_photos', 'id')->where(fn ($query) => $property ? $query->where('property_id', $property->id) : $query)],
            'photo_orders' => ['nullable', 'array'],
            'photo_orders.*' => ['nullable', 'integer', 'min:0'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:4096'],
        ]);

        $data['area_unit'] = $data['area_unit'] ?: 'sqft';

        unset($data['assigned_manager_id'], $data['cover_photo_id'], $data['photo_orders'], $data['photos']);

        return $data;
    }

    private function storeUploadedPhotos(Request $request, Property $property, User $user): void
    {
        $files = $request->file('photos', []);

        if ($files === []) {
            return;
        }

        $nextOrder = (int) $property->photos()->max('sort_order');

        foreach ($files as $file) {
            $nextOrder++;

            $property->photos()->create([
                'disk' => 'public',
                'path' => $file->store('properties/'.$property->id, 'public'),
                'sort_order' => $nextOrder,
                'uploaded_by' => $user->id,
            ]);
        }

        if (! $property->photos()->where('is_cover', true)->exists()) {
            $property->refreshCoverPhoto();
        }
    }

    private function managerOptions()
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'manager'))
            ->orderBy('name')
            ->get();
    }

    private function findManagerOrFail(int $userId): User
    {
        $manager = User::query()->with('roles')->findOrFail($userId);

        abort_unless($manager->hasRole('manager'), 422);

        return $manager;
    }
}
