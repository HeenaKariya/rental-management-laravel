<?php

namespace Tests\Feature\Finance;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyPurchaseLoanTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_purchase_details_and_loan_setup(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('properties.finance.purchase.store', $property), [
                'purchase_price' => 5000000,
                'purchase_date' => '2026-04-01',
                'stamp_duty' => 200000,
                'registration_charges' => 50000,
                'other_acquisition_costs' => 25000,
                'seller_name' => 'Seller One',
                'seller_contact' => '+9100000001',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->post(route('properties.finance.loan.store', $property), [
                'lender_name' => 'Axis Bank',
                'loan_amount' => 3000000,
                'interest_rate' => 8.5,
                'interest_rate_type' => 'floating',
                'loan_start_date' => '2026-04-05',
                'tenure_months' => 120,
                'emi_amount' => 42000,
                'emi_due_day' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('property_purchases', [
            'property_id' => $property->id,
            'total_acquisition_cost' => '5275000.00',
            'seller_name' => 'Seller One',
        ]);

        $this->assertDatabaseHas('property_loans', [
            'property_id' => $property->id,
            'lender_name' => 'Axis Bank',
            'emi_amount' => '42000.00',
        ]);
    }

    public function test_logging_emi_creates_property_ledger_expense_and_updates_summary_metrics(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create(['title' => 'Loan Asset']);

        $this->actingAs($superAdmin)
            ->post(route('properties.finance.loan.store', $property), [
                'lender_name' => 'HDFC',
                'loan_amount' => 1200000,
                'interest_rate' => 9.0,
                'interest_rate_type' => 'fixed',
                'loan_start_date' => '2026-04-01',
                'tenure_months' => 24,
                'emi_amount' => 55000,
                'emi_due_day' => 10,
            ])
            ->assertRedirect();

        $loan = $property->fresh()->loan;

        $this->actingAs($superAdmin)
            ->post(route('properties.finance.loan.emis.store', [$property, $loan]), [
                'amount_paid' => 55000,
                'date_paid' => '2026-04-10',
                'principal_component' => 42000,
                'interest_component' => 13000,
                'outstanding_balance' => 1158000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('property_loan_emi_logs', [
            'property_loan_id' => $loan->id,
            'emi_number' => 1,
            'amount_paid' => '55000.00',
        ]);

        $this->assertDatabaseHas('property_ledger_entries', [
            'property_id' => $property->id,
            'entry_type' => 'expense',
            'category' => 'loan_emi',
            'amount' => '55000.00',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('properties.finance.purchase.show', $property))
            ->assertOk()
            ->assertSee('Total EMI paid')
            ->assertSee('55,000.00')
            ->assertSee('Outstanding principal')
            ->assertSee('1,158,000.00');
    }

    public function test_owner_can_view_owned_purchase_and_loan_page_but_cannot_mutate_data(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $property = Property::factory()->create(['title' => 'Owner Finance Asset']);
        $property->owners()->create([
            'user_id' => $owner->id,
            'ownership_pct' => 100,
            'capital_contribution' => 1000000,
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($owner)
            ->get(route('properties.finance.purchase.show', $property))
            ->assertOk()
            ->assertSee('Owner Finance Asset');

        $this->actingAs($owner)
            ->post(route('properties.finance.purchase.store', $property), [
                'purchase_price' => 1,
            ])
            ->assertForbidden();
    }
}