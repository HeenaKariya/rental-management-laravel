<?php

namespace Tests\Feature\Finance;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentCollectionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_filter_rent_collection_report_and_export_csv_pdf(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create(['title' => 'Collection Property']);
        $unit = Unit::factory()->for($property)->create(['unit_number' => 'RC-101']);
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id, 'full_name' => 'Collection Visible Tenant']);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $lease->ensureRentLedgers($superAdmin);
        $ledger = $lease->rentLedgers()->firstOrFail();

        $ledger->instalments()->create([
            'instalment_number' => 1,
            'amount_paid' => 6000,
            'late_fee_charged' => 250,
            'payment_date' => '2026-04-12',
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'RC-OK-1',
            'recorded_by' => $superAdmin->id,
        ]);

        $hiddenProperty = Property::factory()->create(['title' => 'Collection Hidden Property']);
        $hiddenUnit = Unit::factory()->for($hiddenProperty)->create();
        $hiddenTenant = Tenant::factory()->create(['unit_id' => $hiddenUnit->id, 'full_name' => 'Collection Hidden Tenant']);
        $hiddenLease = Lease::factory()->create([
            'unit_id' => $hiddenUnit->id,
            'tenant_id' => $hiddenTenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $hiddenLease->ensureRentLedgers($superAdmin);
        $hiddenLedger = $hiddenLease->rentLedgers()->firstOrFail();
        $hiddenLedger->instalments()->create([
            'instalment_number' => 1,
            'amount_paid' => 7000,
            'late_fee_charged' => 0,
            'payment_date' => '2026-04-12',
            'payment_mode' => 'cash',
            'reference_number' => 'RC-HIDDEN-1',
            'recorded_by' => $superAdmin->id,
        ]);

        $query = [
            'property_id' => $property->id,
            'payment_mode' => 'bank_transfer',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ];

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.rent-collection.index', $query))
            ->assertOk()
            ->assertSee('Rent collection report')
            ->assertSee($lease->lease_number)
            ->assertSee('Collection Visible Tenant')
            ->assertDontSee($hiddenLease->lease_number)
            ->assertDontSee('Collection Hidden Tenant');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.rent-collection.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.rent-collection.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
