<?php

namespace App\Http\Controllers\Maintenance;

use App\Domain\Notifications\NotificationDeliveryLogger;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\NotificationEventSetting;
use App\Models\PropertyActivityLog;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', MaintenanceRequest::class);

        /** @var User $user */
        $user = $request->user();

        $requests = MaintenanceRequest::query()
            ->visibleTo($user)
            ->with(['tenant', 'unit.property', 'submitter'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')->toString()))
            ->latest()
            ->get();

        return view('maintenance.index', [
            'filters' => $request->only(['status', 'priority']),
            'priorities' => MaintenanceRequest::PRIORITIES,
            'requests' => $requests,
            'statuses' => MaintenanceRequest::STATUSES,
            'user' => $user,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', MaintenanceRequest::class);

        /** @var User $user */
        $user = $request->user();

        return view('maintenance.create', [
            'categories' => MaintenanceRequest::CATEGORIES,
            'priorities' => MaintenanceRequest::PRIORITIES,
            'statuses' => MaintenanceRequest::STATUSES,
            'tenants' => $this->tenantOptionsFor($user),
            'unitOptions' => $this->unitOptionsFor($user),
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MaintenanceRequest::class);

        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedStorePayload($request);

        $unit = $this->findVisibleUnitOrFail($user, (int) $data['unit_id']);

        $tenant = $this->resolveTenantForCreate($user, $unit, $data['tenant_id'] ?? null);

        $maintenanceRequest = MaintenanceRequest::query()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant?->id,
            'submitted_by' => $user->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'description' => $data['description'],
            'status' => 'open',
            'updated_by' => $user->id,
        ]);

        $this->storeUploadedPhotos($request, $maintenanceRequest, $user);

        if ($unit->property) {
            PropertyActivityLog::record($unit->property, 'property.maintenance_request_created', $user, [
                'maintenance_request_id' => $maintenanceRequest->id,
                'priority' => $maintenanceRequest->priority,
                'status' => $maintenanceRequest->status,
                'unit_id' => $unit->id,
            ], $tenant?->user);
        }

        return to_route('maintenance.show', $maintenanceRequest)->with('status', 'Maintenance request created.');
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): View
    {
        $this->authorize('view', $maintenanceRequest);

        $maintenanceRequest->load(['photos.uploader', 'submitter', 'tenant.user', 'unit.property']);

        return view('maintenance.show', [
            'maintenanceRequest' => $maintenanceRequest,
            'statuses' => MaintenanceRequest::STATUSES,
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorize('update', $maintenanceRequest);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(MaintenanceRequest::STATUSES)],
            'internal_notes' => ['nullable', 'string'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'repair_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! $maintenanceRequest->canTransitionTo($data['status']) && $data['status'] !== $maintenanceRequest->status) {
            return back()->withErrors(['status' => 'Invalid status transition.'])->withInput();
        }

        $previousStatus = $maintenanceRequest->status;
        $nextStatus = $data['status'];

        $maintenanceRequest->fill([
            'status' => $nextStatus,
            'internal_notes' => $data['internal_notes'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'repair_cost' => $data['repair_cost'] ?? null,
            'resolved_at' => in_array($nextStatus, ['resolved', 'closed'], true) ? now() : null,
            'updated_by' => $user->id,
        ])->save();

        $maintenanceRequest->loadMissing(['unit.property', 'tenant.user']);

        if ($maintenanceRequest->unit?->property) {
            PropertyActivityLog::record($maintenanceRequest->unit->property, 'property.maintenance_request_updated', $user, [
                'maintenance_request_id' => $maintenanceRequest->id,
                'from_status' => $previousStatus,
                'to_status' => $nextStatus,
                'repair_cost' => $maintenanceRequest->repair_cost,
            ], $maintenanceRequest->tenant?->user);
        }

        $this->notifyMaintenanceStatusChanged($maintenanceRequest, $previousStatus, $nextStatus);

        return to_route('maintenance.show', $maintenanceRequest)->with('status', 'Maintenance request updated.');
    }

    private function notifyMaintenanceStatusChanged(MaintenanceRequest $maintenanceRequest, string $previousStatus, string $nextStatus): void
    {
        if ($previousStatus === $nextStatus) {
            return;
        }

        $eventConfig = NotificationEventSetting::enabledFor('maintenance_request_status_changed', 0);
        if (! $eventConfig['is_enabled']) {
            return;
        }

        $maintenanceRequest->loadMissing(['tenant.user', 'unit.property']);
        $recipient = $maintenanceRequest->tenant?->user;
        $subject = 'Maintenance request status updated';
        $messagePreview = sprintf(
            'Request "%s" moved from %s to %s.',
            $maintenanceRequest->title,
            str($previousStatus)->replace('_', ' ')->title(),
            str($nextStatus)->replace('_', ' ')->title(),
        );
        $payload = [
            'event' => 'maintenance_request_status_changed',
            'maintenance_request_id' => $maintenanceRequest->id,
            'from_status' => $previousStatus,
            'to_status' => $nextStatus,
            'unit_id' => $maintenanceRequest->unit_id,
            'tenant_id' => $maintenanceRequest->tenant_id,
        ];

        /** @var NotificationDeliveryLogger $logger */
        $logger = app(NotificationDeliveryLogger::class);

        if (! $recipient || blank($recipient->email)) {
            $logger->logFailed(
                'maintenance_request_status_changed',
                $recipient,
                $subject,
                $messagePreview,
                'Recipient email is missing for this notification.',
                $payload,
            );

            return;
        }

        $logger->logSent(
            'maintenance_request_status_changed',
            $recipient,
            $subject,
            $messagePreview,
            $payload,
        );
    }

    private function validatedStorePayload(Request $request): array
    {
        return $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(MaintenanceRequest::CATEGORIES)],
            'priority' => ['required', 'string', Rule::in(MaintenanceRequest::PRIORITIES)],
            'description' => ['required', 'string'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
    }

    private function unitOptionsFor(User $user)
    {
        if ($user->hasRole('tenant')) {
            return Unit::query()
                ->whereHas('tenants', fn ($query) => $query->where('user_id', $user->id))
                ->with('property')
                ->orderBy('property_id')
                ->orderBy('unit_number')
                ->get();
        }

        return Unit::query()
            ->visibleTo($user)
            ->with('property')
            ->orderBy('property_id')
            ->orderBy('unit_number')
            ->get();
    }

    private function tenantOptionsFor(User $user)
    {
        if ($user->hasRole('tenant')) {
            return Tenant::query()->where('user_id', $user->id)->with('unit.property')->get();
        }

        return Tenant::query()->visibleTo($user)->with('unit.property')->latest()->limit(200)->get();
    }

    private function findVisibleUnitOrFail(User $user, int $unitId): Unit
    {
        if ($user->hasRole('tenant')) {
            $unit = Unit::query()
                ->whereHas('tenants', fn ($query) => $query->where('user_id', $user->id))
                ->find($unitId);

            abort_unless($unit, 403);

            return $unit;
        }

        $unit = Unit::query()->visibleTo($user)->find($unitId);

        abort_unless($unit, 403);

        return $unit;
    }

    private function resolveTenantForCreate(User $user, Unit $unit, ?int $tenantId): ?Tenant
    {
        if ($user->hasRole('tenant')) {
            return Tenant::query()
                ->where('user_id', $user->id)
                ->where('unit_id', $unit->id)
                ->latest('id')
                ->first();
        }

        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::query()->visibleTo($user)->find($tenantId);

        abort_unless($tenant && $tenant->unit_id === $unit->id, 422);

        return $tenant;
    }

    private function storeUploadedPhotos(Request $request, MaintenanceRequest $maintenanceRequest, User $user): void
    {
        $photos = $request->file('photos', []);

        foreach ($photos as $photo) {
            $maintenanceRequest->photos()->create([
                'disk' => 'public',
                'path' => $photo->store('maintenance/'.$maintenanceRequest->id, 'public'),
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getMimeType(),
                'file_size' => $photo->getSize(),
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ]);
        }
    }
}
