<?php

namespace Tests\Feature\Finance;

use App\Models\Lease;
use App\Models\Property;
use App\Models\RentReturn;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentReturnReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_filter_rent_return_report_and_export_files(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $included = $this->createRentReturnScenario($superAdmin, [
            'status' => 'settled',
            'initiated_at' => '2026-04-10 10:00:00',
            'processed_at' => '2026-04-12 13:00:00',
            'ledger_posted' => true,
        ]);

        $this->createRentReturnScenario($superAdmin, [
            'status' => 'pending_settlement',
            'initiated_at' => '2026-03-01 09:00:00',
            'processed_at' => null,
            'ledger_posted' => false,
        ]);

        $query = [
            'status' => 'settled',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ];

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.rent-returns.index', $query))
            ->assertOk()
            ->assertSee('Rent return report')
            ->assertSee($included->lease->lease_number)
            ->assertSee('Yes')
            ->assertDontSee('pending settlement');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.rent-returns.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.rent-returns.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function createRentReturnScenario(User $actor, array $rentReturnOverrides = []): RentReturn
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'terminated',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'terminated_at' => '2026-04-10 10:00:00',
        ]);

        return RentReturn::query()->create(array_merge([
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'unit_id' => $lease->unit_id,
            'property_id' => $lease->unit->property_id,
            'vacation_date' => '2026-04-10',
            'last_paid_through_date' => '2026-04-30',
            'billing_month' => '2026-04-01',
            'daily_rate' => '333.3333',
            'unused_days' => 20,
            'suggested_amount' => '6666.67',
            'confirmed_amount' => '6500.00',
            'status' => 'settled',
            'settlement_method' => 'cash_refund',
            'settlement_amount' => '6500.00',
            'settlement_date' => '2026-04-12',
            'ledger_posted' => true,
            'initiated_by' => $actor->id,
            'initiated_at' => '2026-04-10 10:00:00',
            'processed_by' => $actor->id,
            'processed_at' => '2026-04-12 13:00:00',
        ], $rentReturnOverrides));
    }
}
