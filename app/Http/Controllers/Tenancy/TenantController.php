<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Tenant::class);

        /** @var User $user */
        $user = $request->user();

        $tenants = Tenant::query()
            ->visibleTo($user)
            ->with(['unit.property', 'documents'])
            ->when($request->filled('unit_id'), fn ($query) => $query->where('unit_id', (int) $request->integer('unit_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('kyc_status'), fn ($query) => $query->where('kyc_status', $request->string('kyc_status')->toString()))
            ->latest()
            ->get();

        return view('tenants.index', [
            'filters' => $request->only(['unit_id', 'status', 'kyc_status']),
            'kycStatusOptions' => Tenant::KYC_STATUSES,
            'statusOptions' => Tenant::STATUSES,
            'tenantUnits' => $this->unitOptionsFor($user),
            'tenants' => $tenants,
            'user' => $user,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Tenant::class);

        return view('tenants.create', [
            'kycStatusOptions' => Tenant::KYC_STATUSES,
            'statusOptions' => Tenant::STATUSES,
            'tenant' => new Tenant(),
            'tenantUnits' => $this->unitOptionsFor($request->user()),
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Tenant::class);

        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPayload($request);

        $unit = $this->findVisibleUnitOrFail($user, (int) $data['unit_id']);

        if ($unit->property?->lifecycle_stage === 'sold') {
            return back()->withInput()->withErrors(['unit_id' => 'This property is sold and cannot accept new tenants.']);
        }

        $tenant = Tenant::query()->create([
            ...$data,
            'unit_id' => $unit->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->storeUploadedDocuments($request, $tenant, $user);

        return to_route('tenants.show', $tenant)->with('status', 'Tenant created.');
    }

    public function show(Request $request, Tenant $tenant): View
    {
        $this->authorize('view', $tenant);

        $tenant->load(['documents.uploader', 'unit.property']);

        return view('tenants.show', [
            'tenant' => $tenant,
            'user' => $request->user(),
        ]);
    }

    public function edit(Request $request, Tenant $tenant): View
    {
        $this->authorize('update', $tenant);

        $tenant->load(['documents', 'unit.property']);

        return view('tenants.edit', [
            'kycStatusOptions' => Tenant::KYC_STATUSES,
            'statusOptions' => Tenant::STATUSES,
            'tenant' => $tenant,
            'tenantUnits' => $this->unitOptionsFor($request->user()),
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPayload($request);

        $unit = $this->findVisibleUnitOrFail($user, (int) $data['unit_id']);

        if ($unit->property?->lifecycle_stage === 'sold') {
            return back()->withInput()->withErrors(['unit_id' => 'This property is sold and cannot accept new tenants.']);
        }

        $tenant->fill([
            ...$data,
            'unit_id' => $unit->id,
            'updated_by' => $user->id,
        ])->save();

        $this->storeUploadedDocuments($request, $tenant, $user);

        return to_route('tenants.show', $tenant)->with('status', 'Tenant updated.');
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string', Rule::in(Tenant::STATUSES)],
            'kyc_status' => ['required', 'string', Rule::in(Tenant::KYC_STATUSES)],
            'move_in_on' => ['nullable', 'date'],
            'move_out_on' => ['nullable', 'date', 'after_or_equal:move_in_on'],
            'notes' => ['nullable', 'string'],
            'kyc_document_type' => ['nullable', 'string', Rule::in(['identity', 'address', 'income', 'other'])],
            'kyc_documents' => ['nullable', 'array'],
            'kyc_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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
            ->whereHas('property', fn ($query) => $query->where('lifecycle_stage', '!=', 'sold'))
            ->get()
            ->sortBy(fn (Unit $unit) => $unit->property->title.'-'.$unit->unit_number)
            ->values();
    }

    private function findVisibleUnitOrFail(User $user, int $unitId): Unit
    {
        $unit = Unit::query()->visibleTo($user)->find($unitId);

        abort_unless($unit, 403);

        return $unit;
    }

    private function storeUploadedDocuments(Request $request, Tenant $tenant, User $user): void
    {
        $files = $request->file('kyc_documents', []);

        if ($files === []) {
            return;
        }

        $documentType = $request->string('kyc_document_type')->toString() ?: 'other';

        foreach ($files as $file) {
            $tenant->documents()->create([
                'document_type' => $documentType,
                'disk' => 'public',
                'path' => $file->store('tenants/'.$tenant->id.'/documents', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ]);
        }
    }
}