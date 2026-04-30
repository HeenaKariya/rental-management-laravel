<?php

namespace Tests\Feature\Finance;

use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyLedgerEntry;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_rent_instalment_auto_posts_income_to_property_ledger(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease();

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $ledger = $lease->fresh()->rentLedgers()->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 5000,
                'payment_date' => '2026-04-10',
                'payment_mode' => 'cash',
                'reference_number' => 'LEDGER-5000',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('property_ledger_entries', [
            'property_id' => $lease->unit->property_id,
            'entry_type' => 'income',
            'category' => 'rent_payment',
            'amount' => '5000.00',
            'status' => 'approved',
        ]);
    }

    public function test_large_expense_is_flagged_for_super_admin_review_and_can_be_approved(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $property = Property::factory()->create();
        $property->assignManager($manager, $superAdmin);

        $this->actingAs($manager)
            ->post(route('properties.finance.ledger.expense.store', $property), [
                'entry_date' => '2026-04-10',
                'category' => 'maintenance',
                'amount' => 75000,
                'vendor_name' => 'FixFast Services',
                'notes' => 'Emergency repair cycle.',
            ])
            ->assertRedirect();

        $expense = PropertyLedgerEntry::query()->where('property_id', $property->id)->where('entry_type', 'expense')->firstOrFail();

        $this->assertSame('pending_review', $expense->status);

        $this->actingAs($superAdmin)
            ->patch(route('properties.finance.ledger.expense.review', [$property, $expense]), [
                'action' => 'approve',
                'review_notes' => 'Validated procurement and amount.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('property_ledger_entries', [
            'id' => $expense->id,
            'status' => 'approved',
        ]);
    }

    public function test_owners_can_view_only_owned_property_ledger_read_only(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $property = Property::factory()->create(['title' => 'Owned Asset']);
        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Asset']);

        $property->owners()->create([
            'user_id' => $owner->id,
            'ownership_pct' => 100,
            'capital_contribution' => 1000000,
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $property->ledgerEntries()->create([
            'entry_type' => 'income',
            'entry_date' => '2026-04-10',
            'category' => 'rent_payment',
            'amount' => 12000,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($owner)
            ->get(route('properties.finance.ledger.index', $property))
            ->assertOk()
            ->assertSee('Owned Asset')
            ->assertSee('Rent Payment');

        $this->actingAs($owner)
            ->get(route('properties.finance.ledger.index', $hiddenProperty))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('properties.finance.ledger.expense.store', $property), [
                'entry_date' => '2026-04-10',
                'category' => 'utility',
                'amount' => 500,
            ])
            ->assertForbidden();
    }

    private function createActiveLease(array $overrides = []): Lease
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);
        $actor = User::factory()->create();

        return Lease::factory()->create(array_merge([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-04-01',
            'end_on' => '2026-04-30',
            'rent_amount' => 10000,
            'billing_day' => 1,
            'grace_period_days' => 0,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 0,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ], $overrides));
    }
}
