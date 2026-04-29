<?php

namespace Tests\Feature\Tenancy;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_super_admin_can_create_a_tenant_with_kyc_document(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $unit = Unit::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('tenants.store'), $this->tenantPayload([
                'unit_id' => $unit->id,
                'kyc_documents' => [UploadedFile::fake()->create('identity-proof.pdf', 120, 'application/pdf')],
            ]))
            ->assertRedirect();

        $tenant = Tenant::query()->firstOrFail();

        $this->assertDatabaseHas('tenant_documents', [
            'tenant_id' => $tenant->id,
            'document_type' => 'identity',
        ]);

        $this->assertTrue(Storage::disk('public')->exists($tenant->documents()->firstOrFail()->path));
    }

    public function test_manager_only_sees_tenants_for_assigned_units_and_cannot_open_unassigned_tenant_urls(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $assignedProperty = Property::factory()->create(['title' => 'Assigned Property']);
        $assignedProperty->assignManager($manager, $manager);
        $assignedUnit = Unit::factory()->create(['property_id' => $assignedProperty->id, 'unit_number' => 'A-110']);
        $assignedTenant = Tenant::factory()->create(['unit_id' => $assignedUnit->id, 'full_name' => 'Assigned Tenant']);

        $hiddenUnit = Unit::factory()->create(['unit_number' => 'B-220']);
        $hiddenTenant = Tenant::factory()->create(['unit_id' => $hiddenUnit->id, 'full_name' => 'Hidden Tenant']);

        $this->actingAs($manager)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('Assigned Tenant')
            ->assertDontSee('Hidden Tenant');

        $this->actingAs($manager)
            ->get(route('tenants.show', $assignedTenant))
            ->assertOk()
            ->assertSee('Assigned Tenant');

        $this->actingAs($manager)
            ->get(route('tenants.show', $hiddenTenant))
            ->assertForbidden();
    }

    public function test_manager_cannot_create_a_tenant_for_an_unassigned_unit(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $unit = Unit::factory()->create();

        $this->actingAs($manager)
            ->post(route('tenants.store'), $this->tenantPayload([
                'unit_id' => $unit->id,
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_manager_can_update_an_assigned_tenant(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $property = Property::factory()->create();
        $property->assignManager($manager, $manager);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'full_name' => 'Initial Tenant']);

        $this->actingAs($manager)
            ->put(route('tenants.update', $tenant), $this->tenantPayload([
                'unit_id' => $unit->id,
                'full_name' => 'Updated Tenant',
                'status' => 'active',
                'kyc_status' => 'verified',
            ]))
            ->assertRedirect(route('tenants.show', $tenant));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'full_name' => 'Updated Tenant',
            'kyc_status' => 'verified',
        ]);
    }

    private function tenantPayload(array $overrides = []): array
    {
        return [
            'unit_id' => Unit::factory()->create()->id,
            'full_name' => 'Sample Tenant',
            'email' => 'tenant@example.com',
            'phone' => '+911234567890',
            'status' => 'prospect',
            'kyc_status' => 'pending',
            'move_in_on' => now()->toDateString(),
            'move_out_on' => null,
            'notes' => 'Initial tenant note.',
            'kyc_document_type' => 'identity',
            ...$overrides,
        ];
    }
}