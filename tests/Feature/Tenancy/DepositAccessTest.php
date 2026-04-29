<?php

namespace Tests\Feature\Tenancy;

use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_deposit_account_with_initial_collection(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease();

        $this->actingAs($superAdmin)
            ->post(route('deposits.store'), [
                'lease_id' => $lease->id,
                'expected_amount' => '25000',
                'initial_collection' => '15000',
                'notes' => 'Initial security deposit.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lease_deposits', [
            'lease_id' => $lease->id,
            'expected_amount' => '25000.00',
            'current_balance' => '15000.00',
            'collected_total' => '15000.00',
        ]);
    }

    public function test_manager_only_sees_deposits_for_assigned_leases_and_cannot_open_unassigned_deposit_urls(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $assignedProperty = Property::factory()->create(['title' => 'Assigned Property']);
        $assignedProperty->assignManager($manager, $manager);
        $assignedUnit = Unit::factory()->create(['property_id' => $assignedProperty->id]);
        $assignedTenant = Tenant::factory()->create(['unit_id' => $assignedUnit->id]);
        $assignedLease = Lease::factory()->create(['unit_id' => $assignedUnit->id, 'tenant_id' => $assignedTenant->id, 'status' => 'active']);
        $assignedDeposit = LeaseDeposit::factory()->create(['lease_id' => $assignedLease->id]);

        $hiddenLease = $this->createActiveLease();
        $hiddenDeposit = LeaseDeposit::factory()->create(['lease_id' => $hiddenLease->id]);

        $this->actingAs($manager)
            ->get(route('deposits.index'))
            ->assertOk()
            ->assertSee($assignedLease->lease_number)
            ->assertDontSee($hiddenLease->lease_number);

        $this->actingAs($manager)
            ->get(route('deposits.show', $assignedDeposit))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('deposits.show', $hiddenDeposit))
            ->assertForbidden();
    }

    public function test_posting_deposit_entries_keeps_the_balance_reconciled(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $deposit = LeaseDeposit::factory()->create(['lease_id' => $this->createActiveLease()->id, 'expected_amount' => 25000]);

        $deposit->postEntry('collection', 20000, $superAdmin, 'Base collection');
        $deposit->postEntry('top_up', 5000, $superAdmin, 'Additional hold');
        $deposit->postEntry('deduction', 3000, $superAdmin, 'Repairs');
        $deposit->postEntry('refund', 2000, $superAdmin, 'Partial return');
        $deposit->postEntry('forfeiture', 1000, $superAdmin, 'Contractual forfeiture');

        $deposit->refresh();

        $this->assertSame('25000.00', $deposit->expected_amount);
        $this->assertSame('19000.00', $deposit->current_balance);
        $this->assertSame('20000.00', $deposit->collected_total);
        $this->assertSame('5000.00', $deposit->top_up_total);
        $this->assertSame('3000.00', $deposit->deducted_total);
        $this->assertSame('2000.00', $deposit->refunded_total);
        $this->assertSame('1000.00', $deposit->forfeited_total);
        $this->assertTrue($deposit->reconciles());
    }

    public function test_balance_reducing_entries_cannot_exceed_current_balance(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $deposit = LeaseDeposit::factory()->create(['lease_id' => $this->createActiveLease()->id]);
        $deposit->postEntry('collection', 4000, $superAdmin, 'Base collection');

        $this->actingAs($superAdmin)
            ->from(route('deposits.show', $deposit))
            ->post(route('deposits.entries.store', $deposit), [
                'entry_type' => 'refund',
                'amount' => '5000',
                'notes' => 'Too high',
            ])
            ->assertSessionHasErrors('amount');
    }

    private function createActiveLease(): Lease
    {
        $unit = Unit::factory()->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);

        return Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
    }
}