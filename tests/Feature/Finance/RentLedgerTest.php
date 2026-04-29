<?php

namespace Tests\Feature\Finance;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_history_generates_monthly_ledgers_for_a_visible_lease(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-03-31',
            'billing_day' => 5,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('leases.payments.show', $lease))
            ->assertOk()
            ->assertSee('January 2026')
            ->assertSee('March 2026');

        $this->assertDatabaseHas('rent_ledgers', [
            'lease_id' => $lease->id,
            'payment_month' => '2026-01-01',
            'due_on' => '2026-01-05',
            'status' => 'overdue',
        ]);

        $this->assertDatabaseCount('rent_ledgers', 3);
    }

    public function test_recording_a_partial_instalment_carries_arrears_forward_to_the_next_month(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-02-28',
            'rent_amount' => 10000,
            'billing_day' => 5,
            'grace_period_days' => 5,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 500,
        ]);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $januaryLedger = $lease->fresh()->rentLedgers()->where('payment_month', '2026-01-01')->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $januaryLedger]), [
                'amount_paid' => 4000,
                'payment_date' => '2026-01-20',
                'payment_mode' => 'bank_transfer',
                'reference_number' => 'UTR-1001',
            ])
            ->assertRedirect();

        $lease = $lease->fresh();
        $januaryLedger = $lease->rentLedgers()->where('payment_month', '2026-01-01')->firstOrFail();
        $februaryLedger = $lease->rentLedgers()->where('payment_month', '2026-02-01')->firstOrFail();

        $this->assertSame('partially_paid', $januaryLedger->status);
        $this->assertSame('6500.00', $januaryLedger->outstanding_balance);
        $this->assertSame('6500.00', $februaryLedger->carried_arrears);
        $this->assertSame('16500.00', $februaryLedger->total_due);
    }

    public function test_overpayment_creates_credit_brought_forward_for_the_next_month(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-02-28',
            'rent_amount' => 10000,
            'billing_day' => 5,
            'grace_period_days' => 0,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 0,
        ]);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $januaryLedger = $lease->fresh()->rentLedgers()->where('payment_month', '2026-01-01')->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $januaryLedger]), [
                'amount_paid' => 11000,
                'payment_date' => '2026-01-03',
                'payment_mode' => 'cash',
            ])
            ->assertRedirect();

        $lease = $lease->fresh();
        $februaryLedger = $lease->rentLedgers()->where('payment_month', '2026-02-01')->firstOrFail();

        $this->assertSame('1000.00', $februaryLedger->credit_brought_forward);
        $this->assertSame('9000.00', $februaryLedger->total_due);
    }

    public function test_tenant_can_view_payment_history_but_cannot_record_instalments(): void
    {
        /** @var User $tenantUser */
        $tenantUser = User::factory()->create();
        $tenantUser->assignRole('tenant');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-01-31',
        ], $tenantUser);

        $this->actingAs($tenantUser)
            ->get(route('leases.payments.show', $lease))
            ->assertOk()
            ->assertSee('read-only mode');

        $ledger = $lease->fresh()->rentLedgers()->firstOrFail();

        $this->actingAs($tenantUser)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 5000,
                'payment_date' => '2026-01-10',
                'payment_mode' => 'cash',
            ])
            ->assertForbidden();
    }

    public function test_staff_can_download_a_pdf_receipt_for_an_instalment(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-01-31',
            'rent_amount' => 10000,
        ]);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $ledger = $lease->fresh()->rentLedgers()->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 6000,
                'payment_date' => '2026-01-10',
                'payment_mode' => 'upi',
                'reference_number' => 'UPI-6000',
            ])
            ->assertRedirect();

        $instalment = $ledger->fresh()->instalments()->firstOrFail();

        $this->actingAs($superAdmin)
            ->get(route('leases.payments.receipt.download', [$lease, $ledger, $instalment]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition');
    }

    public function test_tenant_can_download_their_own_instalment_receipt_pdf(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $tenantUser */
        $tenantUser = User::factory()->create();
        $tenantUser->assignRole('tenant');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-01-31',
        ], $tenantUser);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $ledger = $lease->fresh()->rentLedgers()->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 5000,
                'payment_date' => '2026-01-10',
                'payment_mode' => 'bank_transfer',
            ])
            ->assertRedirect();

        $instalment = $ledger->fresh()->instalments()->firstOrFail();

        $this->actingAs($tenantUser)
            ->get(route('leases.payments.receipt.download', [$lease, $ledger, $instalment]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_staff_can_correct_payment_mode_and_reference_with_audit_log_without_changing_amount(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-01-31',
        ]);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $ledger = $lease->fresh()->rentLedgers()->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 5000,
                'payment_date' => '2026-01-10',
                'payment_mode' => 'cash',
                'reference_number' => 'OLD-REF',
            ])
            ->assertRedirect();

        $instalment = $ledger->fresh()->instalments()->firstOrFail();

        $this->actingAs($superAdmin)
            ->patch(route('leases.payments.instalments.correct', [$lease, $ledger, $instalment]), [
                'payment_mode' => 'bank_transfer',
                'reference_number' => 'NEW-REF',
                'amount_paid' => 99999,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rent_instalments', [
            'id' => $instalment->id,
            'amount_paid' => '5000.00',
            'payment_mode' => 'bank_transfer',
            'reference_number' => 'NEW-REF',
        ]);

        $this->assertDatabaseHas('rent_instalment_corrections', [
            'rent_instalment_id' => $instalment->id,
            'field_name' => 'payment_mode',
            'old_value' => 'cash',
            'new_value' => 'bank_transfer',
        ]);

        $this->assertDatabaseHas('rent_instalment_corrections', [
            'rent_instalment_id' => $instalment->id,
            'field_name' => 'reference_number',
            'old_value' => 'OLD-REF',
            'new_value' => 'NEW-REF',
        ]);
    }

    public function test_voiding_an_instalment_requires_password_confirmation_and_recalculates_ledger(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-02-28',
            'rent_amount' => 10000,
            'late_fee_mode' => 'fixed',
            'late_fee_value' => 0,
            'grace_period_days' => 0,
        ]);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $ledger = $lease->fresh()->rentLedgers()->where('payment_month', '2026-01-01')->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 6000,
                'payment_date' => '2026-01-10',
                'payment_mode' => 'cash',
            ])
            ->assertRedirect();

        $instalment = $ledger->fresh()->instalments()->firstOrFail();

        $this->actingAs($superAdmin)
            ->delete(route('leases.payments.instalments.void', [$lease, $ledger, $instalment]), [
                'void_reason' => 'Recorded against the wrong month.',
            ])
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->delete(route('leases.payments.instalments.void', [$lease, $ledger, $instalment]), [
                'void_reason' => 'Recorded against the wrong month.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rent_instalments', [
            'id' => $instalment->id,
            'void_reason' => 'Recorded against the wrong month.',
        ]);

        $this->assertDatabaseHas('rent_instalment_corrections', [
            'rent_instalment_id' => $instalment->id,
            'field_name' => 'voided',
            'old_value' => 'active',
            'new_value' => 'Recorded against the wrong month.',
        ]);

        $lease = $lease->fresh();
        $januaryLedger = $lease->rentLedgers()->where('payment_month', '2026-01-01')->firstOrFail();
        $februaryLedger = $lease->rentLedgers()->where('payment_month', '2026-02-01')->firstOrFail();

        $this->assertSame('0.00', $januaryLedger->total_received);
        $this->assertSame('10000.00', $januaryLedger->outstanding_balance);
        $this->assertSame('10000.00', $februaryLedger->carried_arrears);
    }

    public function test_voided_instalments_are_terminal_and_cannot_be_corrected_again(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $lease = $this->createActiveLease([
            'start_on' => '2026-01-01',
            'end_on' => '2026-01-31',
        ]);

        $this->actingAs($superAdmin)->get(route('leases.payments.show', $lease))->assertOk();
        $ledger = $lease->fresh()->rentLedgers()->firstOrFail();

        $this->actingAs($superAdmin)
            ->post(route('leases.payments.instalments.store', [$lease, $ledger]), [
                'amount_paid' => 2500,
                'payment_date' => '2026-01-10',
                'payment_mode' => 'cash',
            ])
            ->assertRedirect();

        $instalment = $ledger->fresh()->instalments()->firstOrFail();

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->delete(route('leases.payments.instalments.void', [$lease, $ledger, $instalment]), [
                'void_reason' => 'Duplicate entry.',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->patch(route('leases.payments.instalments.correct', [$lease, $ledger, $instalment]), [
                'payment_mode' => 'upi',
                'reference_number' => 'AFTER-VOID',
            ])
            ->assertSessionHasErrors('payment_mode');
    }

    private function createActiveLease(array $overrides = [], ?User $tenantUser = null): Lease
    {
        $property = Property::factory()->create();
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $tenant = Tenant::factory()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenantUser?->id,
        ]);

        return Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'active_lease_guard' => 1,
            ...$overrides,
        ]);
    }
}