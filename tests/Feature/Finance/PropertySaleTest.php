<?php

namespace Tests\Feature\Finance;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_property_track_leads_and_close_sale_with_profit_calculation(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $property = Property::factory()->create();
        $property->purchase()->create([
            'purchase_price' => 5000000,
            'purchase_date' => '2026-01-01',
            'stamp_duty' => 100000,
            'registration_charges' => 50000,
            'other_acquisition_costs' => 50000,
            'total_acquisition_cost' => 5200000,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $property->owners()->create([
            'user_id' => $owner->id,
            'ownership_pct' => 100,
            'capital_contribution' => 5200000,
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('properties.finance.sale.store', $property), [
                'listing_date' => '2026-04-01',
                'asking_price' => 6500000,
                'broker_name' => 'Broker One',
            ])
            ->assertRedirect();

        $sale = $property->fresh()->sale;

        $this->actingAs($superAdmin)
            ->post(route('properties.finance.sale.leads.store', [$property, $sale]), [
                'buyer_name' => 'Buyer One',
                'buyer_contact' => '+9100000002',
                'inquiry_date' => '2026-04-03',
                'offer_amount' => 6300000,
                'offer_date' => '2026-04-04',
                'status' => 'offer_made',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->post(route('properties.finance.sale.close', [$property, $sale]), [
                'final_sale_price' => 6400000,
                'sale_date' => '2026-04-10',
                'buyer_name' => 'Buyer One',
                'buyer_contact' => '+9100000002',
                'broker_commission' => 100000,
                'closing_costs' => 50000,
                'sale_notes' => 'Closed after negotiation.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('property_sales', [
            'property_id' => $property->id,
            'status' => 'closed',
            'net_sale_proceeds' => '6250000.00',
            'gross_profit_loss' => '1050000.00',
        ]);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'lifecycle_stage' => 'sold',
        ]);

        $this->actingAs($owner)
            ->get(route('properties.finance.sale.show', $property))
            ->assertOk()
            ->assertSee('1,050,000.00');
    }

    public function test_sold_property_blocks_new_tenant_and_lease_creation(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create(['lifecycle_stage' => 'sold']);
        $unit = Unit::factory()->create(['property_id' => $property->id]);

        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($superAdmin)
            ->from(route('tenants.create'))
            ->post(route('tenants.store'), [
                'unit_id' => $unit->id,
                'full_name' => 'Blocked Tenant',
                'email' => 'blocked@example.com',
                'phone' => '+9100000003',
                'status' => 'prospect',
                'kyc_status' => 'pending',
            ])
            ->assertSessionHasErrors('unit_id');

        $this->actingAs($superAdmin)
            ->from(route('leases.create'))
            ->post(route('leases.store'), [
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'start_on' => '2026-05-01',
                'end_on' => '2027-04-30',
                'rent_amount' => 10000,
                'billing_day' => 5,
                'grace_period_days' => 5,
                'late_fee_mode' => 'fixed',
                'late_fee_value' => 500,
                'status' => 'draft',
                'notes' => 'Should be blocked.',
            ])
            ->assertSessionHasErrors('unit_id');
    }
}