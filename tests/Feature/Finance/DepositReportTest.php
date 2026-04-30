<?php

namespace Tests\Feature\Finance;

use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_filter_deposit_report_and_export_csv_pdf(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create(['title' => 'Report Deposit Property']);
        $unit = Unit::factory()->for($property)->create(['unit_number' => 'D-101']);
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'full_name' => 'Deposit Visible Tenant']);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $includedDeposit = LeaseDeposit::factory()->create([
            'lease_id' => $lease->id,
            'expected_amount' => 25000,
            'status' => 'open',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $includedDeposit->postEntry('collection', 20000, $superAdmin, 'Base collection');
        $includedDeposit->postEntry('deduction', 3000, $superAdmin, 'Repairs');

        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Deposit Property']);
        $hiddenUnit = Unit::factory()->for($hiddenProperty)->create();
        $hiddenTenant = Tenant::factory()->create(['unit_id' => $hiddenUnit->id, 'full_name' => 'Deposit Hidden Tenant']);
        $hiddenLease = Lease::factory()->create([
            'unit_id' => $hiddenUnit->id,
            'tenant_id' => $hiddenTenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $hiddenDeposit = LeaseDeposit::factory()->create([
            'lease_id' => $hiddenLease->id,
            'expected_amount' => 22000,
            'status' => 'open',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $hiddenDeposit->postEntry('collection', 22000, $superAdmin, 'Hidden collection');
        $hiddenDeposit->postEntry('refund', 5000, $superAdmin, 'Hidden refund');

        $query = [
            'property_id' => $property->id,
            'status' => 'open',
            'entry_type' => 'deduction',
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ];

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.deposits.index', $query))
            ->assertOk()
            ->assertSee('Deposits report')
            ->assertSee($lease->lease_number)
            ->assertSee('Deposit Visible Tenant')
            ->assertDontSee($hiddenLease->lease_number)
            ->assertDontSee('Deposit Hidden Tenant');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.deposits.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.deposits.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_only_sees_deposit_report_rows_for_assigned_property(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $visibleProperty = Property::factory()->create(['title' => 'Visible Deposit Report Property']);
        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Deposit Report Property']);
        $visibleProperty->assignManager($manager, $superAdmin);

        $visibleUnit = Unit::factory()->for($visibleProperty)->create();
        $visibleTenant = Tenant::factory()->create(['unit_id' => $visibleUnit->id, 'full_name' => 'Visible Deposit Report Tenant']);
        $visibleLease = Lease::factory()->create([
            'unit_id' => $visibleUnit->id,
            'tenant_id' => $visibleTenant->id,
            'status' => 'active',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $visibleDeposit = LeaseDeposit::factory()->create([
            'lease_id' => $visibleLease->id,
            'status' => 'open',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $visibleDeposit->postEntry('collection', 12000, $superAdmin, 'Visible collection');

        $hiddenUnit = Unit::factory()->for($hiddenProperty)->create();
        $hiddenTenant = Tenant::factory()->create(['unit_id' => $hiddenUnit->id, 'full_name' => 'Hidden Deposit Report Tenant']);
        $hiddenLease = Lease::factory()->create([
            'unit_id' => $hiddenUnit->id,
            'tenant_id' => $hiddenTenant->id,
            'status' => 'active',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $hiddenDeposit = LeaseDeposit::factory()->create([
            'lease_id' => $hiddenLease->id,
            'status' => 'open',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $hiddenDeposit->postEntry('collection', 13000, $superAdmin, 'Hidden collection');

        $this->actingAs($manager)
            ->get(route('finance.reports.deposits.index'))
            ->assertOk()
            ->assertSee('Visible Deposit Report Property')
            ->assertSee('Visible Deposit Report Tenant')
            ->assertDontSee('Hidden Deposit Report Property')
            ->assertDontSee('Hidden Deposit Report Tenant');
    }
}
