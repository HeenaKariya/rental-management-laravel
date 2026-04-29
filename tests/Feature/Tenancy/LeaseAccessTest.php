<?php

namespace Tests\Feature\Tenancy;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_an_active_lease_for_a_visible_unit_and_tenant(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $unit = Unit::factory()->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($superAdmin)
            ->post(route('leases.store'), $this->leasePayload([
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'status' => 'active',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'active_lease_guard' => 1,
        ]);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'occupancy_status' => 'occupied',
        ]);
    }

    public function test_database_enforces_a_single_active_lease_per_unit(): void
    {
        $unit = Unit::factory()->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);

        Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'active_lease_guard' => 1,
        ]);

        $this->expectException(QueryException::class);

        Lease::query()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_on' => now()->addMonth()->toDateString(),
            'end_on' => now()->addYear()->toDateString(),
            'rent_amount' => 12000,
            'billing_day' => 5,
            'status' => 'active',
        ]);
    }

    public function test_manager_only_sees_leases_for_assigned_units_and_cannot_open_unassigned_lease_urls(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $assignedProperty = Property::factory()->create(['title' => 'Assigned Property']);
        $assignedProperty->assignManager($manager, $manager);
        $assignedUnit = Unit::factory()->create(['property_id' => $assignedProperty->id, 'unit_number' => 'L-101']);
        $assignedTenant = Tenant::factory()->create(['unit_id' => $assignedUnit->id, 'full_name' => 'Lease Tenant']);
        $assignedLease = Lease::factory()->create([
            'unit_id' => $assignedUnit->id,
            'tenant_id' => $assignedTenant->id,
            'status' => 'active',
        ]);

        $hiddenUnit = Unit::factory()->create(['unit_number' => 'L-202']);
        $hiddenTenant = Tenant::factory()->create(['unit_id' => $hiddenUnit->id]);
        $hiddenLease = Lease::factory()->create([
            'unit_id' => $hiddenUnit->id,
            'tenant_id' => $hiddenTenant->id,
            'status' => 'draft',
        ]);

        $this->actingAs($manager)
            ->get(route('leases.index'))
            ->assertOk()
            ->assertSee($assignedLease->lease_number)
            ->assertDontSee($hiddenLease->lease_number);

        $this->actingAs($manager)
            ->get(route('leases.show', $assignedLease))
            ->assertOk()
            ->assertSee($assignedLease->lease_number);

        $this->actingAs($manager)
            ->get(route('leases.show', $hiddenLease))
            ->assertForbidden();
    }

    public function test_super_admin_can_renew_an_active_lease_and_mark_the_previous_record_renewed(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $unit = Unit::factory()->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);
        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('leases.renew', $lease), [
                'start_on' => '2027-01-01',
                'end_on' => '2027-12-31',
                'rent_amount' => '15500',
                'billing_day' => 5,
                'notes' => 'Renewed for the next term.',
            ])
            ->assertRedirect(route('leases.index'));

        $this->assertDatabaseHas('leases', [
            'id' => $lease->id,
            'status' => 'renewed',
            'active_lease_guard' => null,
        ]);

        $this->assertDatabaseHas('leases', [
            'previous_lease_id' => $lease->id,
            'status' => 'active',
            'start_on' => '2027-01-01',
            'active_lease_guard' => 1,
        ]);
    }

    public function test_renewal_cannot_create_an_overlapping_successor_lease(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $unit = Unit::factory()->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);
        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
        ]);

        $this->actingAs($superAdmin)
            ->from(route('leases.show', $lease))
            ->post(route('leases.renew', $lease), [
                'start_on' => '2026-12-01',
                'end_on' => '2027-11-30',
                'rent_amount' => '15500',
                'billing_day' => 5,
            ])
            ->assertSessionHasErrors('start_on');
    }

    private function leasePayload(array $overrides = []): array
    {
        return [
            'unit_id' => Unit::factory()->create()->id,
            'tenant_id' => Tenant::factory()->create()->id,
            'start_on' => now()->startOfMonth()->toDateString(),
            'end_on' => now()->addYear()->subDay()->toDateString(),
            'rent_amount' => '12000',
            'billing_day' => 5,
            'status' => 'draft',
            'notes' => 'Initial lease seed.',
            ...$overrides,
        ];
    }
}