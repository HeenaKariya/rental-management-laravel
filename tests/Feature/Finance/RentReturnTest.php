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

class RentReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_initiate_a_rent_return_from_a_terminated_lease_with_detected_overpayment(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createTerminatedLease([
            'start_on' => '2026-04-01',
            'end_on' => '2026-04-30',
            'rent_amount' => 10000,
            'billing_day' => 1,
            'grace_period_days' => 0,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 0,
            'terminated_at' => '2026-04-10 10:00:00',
        ]);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $ledger = $lease->fresh()->rentLedgers()->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 10000,
                'payment_date' => '2026-04-01',
                'payment_mode' => 'bank_transfer',
                'reference_number' => 'APR-PAID',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->get(route('leases.show', $lease))
            ->assertOk()
            ->assertSee('Process Rent Return');

        $this->actingAs($superAdmin)
            ->get(route('leases.rent-return.create', $lease))
            ->assertOk()
            ->assertSee('6,666.67')
            ->assertSee('2026-04-10')
            ->assertSee('2026-04-30');
    }

    public function test_staff_can_confirm_and_settle_a_rent_return_with_an_override_reason(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createTerminatedLease([
            'start_on' => '2026-04-01',
            'end_on' => '2026-04-30',
            'rent_amount' => 10000,
            'billing_day' => 1,
            'grace_period_days' => 0,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 0,
            'terminated_at' => '2026-04-10 10:00:00',
        ]);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $ledger = $lease->fresh()->rentLedgers()->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 10000,
                'payment_date' => '2026-04-01',
                'payment_mode' => 'cash',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->post(route('leases.rent-return.store', $lease), [
                'vacation_date' => '2026-04-10',
                'last_paid_through_date' => '2026-04-30',
                'monthly_rent_amount' => 10000,
                'billing_month_days' => 30,
                'notes' => 'Tenant vacated early.',
            ])
            ->assertRedirect();

        $rentReturn = $lease->fresh()->rentReturn;

        $this->actingAs($superAdmin)
            ->patch(route('leases.rent-return.update', [$lease, $rentReturn]), [
                'action' => 'settle',
                'vacation_date' => '2026-04-10',
                'last_paid_through_date' => '2026-04-30',
                'monthly_rent_amount' => 10000,
                'billing_month_days' => 30,
                'confirmed_amount' => 6500,
                'override_reason' => 'Cleaning fee absorbed before refund.',
                'settlement_method' => 'cash_refund',
                'settlement_amount' => 6500,
                'settlement_date' => '2026-04-12',
                'settlement_reference' => 'RR-APR-6500',
                'settlement_details' => 'Refund released from branch cash desk.',
                'ledger_posted' => '1',
                'notes' => 'Tenant vacated early.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rent_returns', [
            'lease_id' => $lease->id,
            'status' => 'settled',
            'suggested_amount' => '6666.67',
            'confirmed_amount' => '6500.00',
            'override_reason' => 'Cleaning fee absorbed before refund.',
            'settlement_method' => 'cash_refund',
            'settlement_amount' => '6500.00',
            'settlement_reference' => 'RR-APR-6500',
            'ledger_posted' => 1,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('leases.rent-return.summary.download', [$lease, $rentReturn->fresh()]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_tenant_can_view_a_confirmed_rent_return_but_cannot_update_it(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $tenantUser */
        $tenantUser = User::factory()->create();
        $tenantUser->assignRole('tenant');

        $lease = $this->createTerminatedLease([
            'start_on' => '2026-04-01',
            'end_on' => '2026-04-30',
            'rent_amount' => 10000,
            'terminated_at' => '2026-04-10 10:00:00',
        ], $tenantUser);

        $rentReturn = RentReturn::query()->create([
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
            'confirmed_amount' => '6666.67',
            'status' => 'confirmed',
            'settlement_method' => null,
            'ledger_posted' => false,
            'initiated_by' => $superAdmin->id,
            'initiated_at' => now()->subDay(),
            'processed_by' => $superAdmin->id,
            'processed_at' => now()->subDay(),
        ]);

        $this->actingAs($tenantUser)
            ->get(route('leases.show', $lease))
            ->assertOk()
            ->assertSee('Rent return');

        $this->actingAs($tenantUser)
            ->get(route('leases.rent-return.show', [$lease, $rentReturn]))
            ->assertOk()
            ->assertSee('Confirmed')
            ->assertSee('Download summary');

        $this->actingAs($tenantUser)
            ->get(route('leases.rent-return.summary.download', [$lease, $rentReturn]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($tenantUser)
            ->patch(route('leases.rent-return.update', [$lease, $rentReturn]), [
                'action' => 'confirm',
                'vacation_date' => '2026-04-10',
                'last_paid_through_date' => '2026-04-30',
                'monthly_rent_amount' => 10000,
                'billing_month_days' => 30,
                'confirmed_amount' => 6666.67,
            ])
            ->assertForbidden();
    }

    private function createTerminatedLease(array $overrides = [], ?User $tenantUser = null): Lease
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->for($property)->create();

        $tenantAttributes = ['unit_id' => $unit->id];

        if ($tenantUser instanceof User) {
            $tenantAttributes['user_id'] = $tenantUser->id;
        }

        $tenant = Tenant::factory()->create($tenantAttributes);
        $actor = User::factory()->create();

        return Lease::factory()->create(array_merge([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'terminated',
            'start_on' => '2026-04-01',
            'end_on' => '2026-04-30',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'terminated_at' => '2026-04-10 10:00:00',
        ], $overrides));
    }
}