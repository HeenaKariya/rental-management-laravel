<?php

namespace Tests\Feature\Finance;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_rent_dashboard_shows_all_required_views_and_filters(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 29)->startOfDay());

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $propertyA = Property::factory()->create(['title' => 'Harbor One']);
        $unitA = Unit::factory()->create(['property_id' => $propertyA->id, 'unit_number' => 'A-101']);
        $tenantA = Tenant::factory()->create(['unit_id' => $unitA->id, 'full_name' => 'Tenant Alpha']);
        $leaseA = Lease::factory()->create([
            'unit_id' => $unitA->id,
            'tenant_id' => $tenantA->id,
            'status' => 'active',
            'active_lease_guard' => 1,
            'start_on' => '2026-04-01',
            'end_on' => '2026-06-30',
            'billing_day' => 5,
            'rent_amount' => 10000,
            'grace_period_days' => 3,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 500,
        ]);

        $propertyB = Property::factory()->create(['title' => 'Garden Court']);
        $unitB = Unit::factory()->create(['property_id' => $propertyB->id, 'unit_number' => 'B-202']);
        $tenantB = Tenant::factory()->create(['unit_id' => $unitB->id, 'full_name' => 'Tenant Beta']);
        $leaseB = Lease::factory()->create([
            'unit_id' => $unitB->id,
            'tenant_id' => $tenantB->id,
            'status' => 'active',
            'active_lease_guard' => 1,
            'start_on' => '2026-03-01',
            'end_on' => '2026-05-31',
            'billing_day' => 5,
            'rent_amount' => 12000,
            'grace_period_days' => 3,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 0,
        ]);

        $this->actingAs($superAdmin)->get(route('finance.index'))->assertOk();

        $aprilLedger = $leaseA->fresh()->rentLedgers()->where('payment_month', '2026-04-01')->firstOrFail();
        $mayLedger = $leaseA->fresh()->rentLedgers()->where('payment_month', '2026-05-01')->firstOrFail();
        $marchLedger = $leaseB->fresh()->rentLedgers()->where('payment_month', '2026-03-01')->firstOrFail();

        $this->actingAs($superAdmin)->post(route('leases.payments.instalments.store', [$leaseA, $aprilLedger]), [
            'amount_paid' => 4000,
            'payment_date' => '2026-04-29',
            'payment_mode' => 'cash',
        ])->assertRedirect();

        $this->actingAs($superAdmin)->post(route('leases.payments.instalments.store', [$leaseB, $marchLedger]), [
            'amount_paid' => 3000,
            'payment_date' => '2026-04-28',
            'payment_mode' => 'bank_transfer',
        ])->assertRedirect();

        $response = $this->actingAs($superAdmin)->get(route('finance.index', [
            'property_id' => $propertyA->id,
            'unit_id' => $unitA->id,
        ]));

        $response->assertOk()
            ->assertSee('Upcoming dues')
            ->assertSee('Partially paid')
            ->assertSee('Overdue')
            ->assertSee('Arrears tracker')
            ->assertSee('Recently recorded')
            ->assertSee('Tenant Alpha')
            ->assertDontSee('Tenant Beta')
            ->assertSee('Harbor One')
            ->assertSee('A-101');

        $this->travelBack();
    }

    public function test_manager_rent_dashboard_is_scoped_to_assigned_properties(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 29)->startOfDay());

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $assignedProperty = Property::factory()->create(['title' => 'Assigned Plaza']);
        $assignedProperty->assignManager($manager, $manager);
        $assignedUnit = Unit::factory()->create(['property_id' => $assignedProperty->id, 'unit_number' => 'P-11']);
        $assignedTenant = Tenant::factory()->create(['unit_id' => $assignedUnit->id, 'full_name' => 'Visible Finance Tenant']);
        $assignedLease = Lease::factory()->create([
            'unit_id' => $assignedUnit->id,
            'tenant_id' => $assignedTenant->id,
            'status' => 'active',
            'active_lease_guard' => 1,
            'start_on' => '2026-04-01',
            'end_on' => '2026-05-31',
            'billing_day' => 5,
            'rent_amount' => 9000,
            'grace_period_days' => 3,
        ]);

        $hiddenUnit = Unit::factory()->create(['unit_number' => 'P-99']);
        $hiddenTenant = Tenant::factory()->create(['unit_id' => $hiddenUnit->id, 'full_name' => 'Hidden Finance Tenant']);
        Lease::factory()->create([
            'unit_id' => $hiddenUnit->id,
            'tenant_id' => $hiddenTenant->id,
            'status' => 'active',
            'active_lease_guard' => 1,
            'start_on' => '2026-04-01',
            'end_on' => '2026-05-31',
            'billing_day' => 5,
            'rent_amount' => 9000,
            'grace_period_days' => 3,
        ]);

        $this->actingAs($manager)->get(route('finance.index'))->assertOk();
        $assignedLedger = $assignedLease->fresh()->rentLedgers()->where('payment_month', '2026-04-01')->firstOrFail();

        $this->actingAs($manager)->post(route('leases.payments.instalments.store', [$assignedLease, $assignedLedger]), [
            'amount_paid' => 2500,
            'payment_date' => '2026-04-28',
            'payment_mode' => 'cash',
        ])->assertRedirect();

        $this->actingAs($manager)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Visible Finance Tenant')
            ->assertDontSee('Hidden Finance Tenant')
            ->assertSee('Assigned Plaza')
            ->assertDontSee('P-99');

        $this->travelBack();
    }
}