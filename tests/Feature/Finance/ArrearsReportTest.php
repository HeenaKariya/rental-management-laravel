<?php

namespace Tests\Feature\Finance;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArrearsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_filter_arrears_report_and_export_csv_pdf(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create(['title' => 'Arrears Tower']);
        $unit = Unit::factory()->for($property)->create(['unit_number' => 'A-101']);
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'full_name' => 'Visible Tenant']);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'rent_amount' => 12000,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $lease->rentLedgers()->createMany([
            [
                'payment_month' => '2026-01-01',
                'due_on' => '2026-01-05',
                'base_rent_amount' => 12000,
                'carried_arrears' => 1000,
                'credit_brought_forward' => 0,
                'total_due' => 13000,
                'total_received' => 7000,
                'late_fee_total' => 0,
                'outstanding_balance' => 6000,
                'status' => 'partially_paid',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ],
            [
                'payment_month' => '2026-02-01',
                'due_on' => '2026-02-05',
                'base_rent_amount' => 12000,
                'carried_arrears' => 6000,
                'credit_brought_forward' => 0,
                'total_due' => 18000,
                'total_received' => 9000,
                'late_fee_total' => 0,
                'outstanding_balance' => 9000,
                'status' => 'overdue',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ],
            [
                'payment_month' => '2026-03-01',
                'due_on' => '2026-03-05',
                'base_rent_amount' => 12000,
                'carried_arrears' => 9000,
                'credit_brought_forward' => 0,
                'total_due' => 21000,
                'total_received' => 0,
                'late_fee_total' => 0,
                'outstanding_balance' => 21000,
                'status' => 'overdue',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ],
        ]);

        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Tower']);
        $hiddenUnit = Unit::factory()->for($hiddenProperty)->create();
        $hiddenTenant = Tenant::factory()->create(['unit_id' => $hiddenUnit->id, 'full_name' => 'Hidden Tenant']);
        $hiddenLease = Lease::factory()->create([
            'unit_id' => $hiddenUnit->id,
            'tenant_id' => $hiddenTenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'rent_amount' => 10000,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $hiddenLease->rentLedgers()->create([
            'payment_month' => '2026-03-01',
            'due_on' => '2026-03-05',
            'base_rent_amount' => 10000,
            'carried_arrears' => 0,
            'credit_brought_forward' => 0,
            'total_due' => 10000,
            'total_received' => 0,
            'late_fee_total' => 0,
            'outstanding_balance' => 10000,
            'status' => 'overdue',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $query = [
            'property_id' => $property->id,
            'status' => 'overdue',
            'date_from' => '2026-02-01',
            'date_to' => '2026-03-31',
            'alert_threshold_months' => 2,
        ];

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.arrears.index', $query))
            ->assertOk()
            ->assertSee('Arrears and partial payments report')
            ->assertSee('Arrears Tower')
            ->assertSee($lease->lease_number)
            ->assertSee('Alert (3 months)')
            ->assertDontSee('Hidden Tenant');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.arrears.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.arrears.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_only_sees_arrears_for_assigned_property(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $visibleProperty = Property::factory()->create(['title' => 'Visible Arrears Property']);
        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Arrears Property']);
        $visibleProperty->assignManager($manager, $superAdmin);

        $visibleUnit = Unit::factory()->for($visibleProperty)->create();
        $visibleTenant = Tenant::factory()->create(['unit_id' => $visibleUnit->id, 'full_name' => 'Visible Arrears Tenant']);
        $visibleLease = Lease::factory()->create([
            'unit_id' => $visibleUnit->id,
            'tenant_id' => $visibleTenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $visibleLease->rentLedgers()->create([
            'payment_month' => '2026-04-01',
            'due_on' => '2026-04-05',
            'base_rent_amount' => 10000,
            'carried_arrears' => 2000,
            'credit_brought_forward' => 0,
            'total_due' => 12000,
            'total_received' => 2000,
            'late_fee_total' => 0,
            'outstanding_balance' => 10000,
            'status' => 'overdue',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $hiddenUnit = Unit::factory()->for($hiddenProperty)->create();
        $hiddenTenant = Tenant::factory()->create(['unit_id' => $hiddenUnit->id, 'full_name' => 'Hidden Arrears Tenant']);
        $hiddenLease = Lease::factory()->create([
            'unit_id' => $hiddenUnit->id,
            'tenant_id' => $hiddenTenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $hiddenLease->rentLedgers()->create([
            'payment_month' => '2026-04-01',
            'due_on' => '2026-04-05',
            'base_rent_amount' => 9000,
            'carried_arrears' => 1500,
            'credit_brought_forward' => 0,
            'total_due' => 10500,
            'total_received' => 0,
            'late_fee_total' => 0,
            'outstanding_balance' => 10500,
            'status' => 'overdue',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($manager)
            ->get(route('finance.reports.arrears.index'))
            ->assertOk()
            ->assertSee('Visible Arrears Property')
            ->assertSee('Visible Arrears Tenant')
            ->assertDontSee('Hidden Arrears Property')
            ->assertDontSee('Hidden Arrears Tenant');
    }
}
