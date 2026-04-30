<?php

namespace Tests\Feature\Finance;

use App\Domain\Notifications\ReminderNotificationService;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\NotificationDelivery;
use App\Models\NotificationEventSetting;
use App\Models\Property;
use App\Models\PropertyLoan;
use App\Models\RentLedger;
use App\Models\RentReturn;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReminderNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_command_creates_delivery_logs_for_due_rent_event(): void
    {
        Carbon::setTestNow('2026-05-01 09:00:00');

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['email' => 'admin@example.test']);
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create(['email' => 'manager@example.test']);
        $manager->assignRole('manager');

        /** @var User $tenantUser */
        $tenantUser = User::factory()->create(['email' => 'tenant@example.test']);
        $tenantUser->assignRole('tenant');

        $property = Property::factory()->create();
        $property->assignManager($manager, $superAdmin);

        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenantUser->id,
            'kyc_status' => 'verified',
        ]);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-12-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        RentLedger::query()->create([
            'lease_id' => $lease->id,
            'payment_month' => '2026-05-01',
            'due_on' => '2026-05-04',
            'base_rent_amount' => 10000,
            'carried_arrears' => 0,
            'credit_brought_forward' => 0,
            'total_due' => 10000,
            'total_received' => 0,
            'late_fee_total' => 0,
            'outstanding_balance' => 10000,
            'status' => 'unpaid',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->artisan('phase7:dispatch-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'rent_due_reminder',
            'status' => 'sent',
            'recipient_email' => 'tenant@example.test',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'rent_due_reminder',
            'status' => 'sent',
            'recipient_email' => 'manager@example.test',
        ]);

        Carbon::setTestNow();
    }

    public function test_retry_command_resolves_failed_delivery_after_email_is_restored(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => '']);

        NotificationDelivery::query()->create([
            'event_key' => 'rent_due_reminder',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'recipient_email' => null,
            'channel' => 'email',
            'status' => 'failed',
            'subject' => 'Rent due reminder',
            'message_preview' => 'Rent is due soon.',
            'failure_reason' => 'Recipient email is missing for this notification.',
            'failed_at' => now(),
            'retry_count' => 1,
        ]);

        $user->forceFill(['email' => 'restored@example.test'])->save();

        $this->artisan('phase7:retry-failed-notifications')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'rent_due_reminder',
            'status' => 'sent',
            'recipient_email' => 'restored@example.test',
            'retry_count' => 2,
        ]);
    }

    public function test_dispatch_command_creates_delivery_logs_for_lease_expiring_event(): void
    {
        Carbon::setTestNow('2026-05-01 09:00:00');
        $this->configureOnlyEvent('lease_expiring_soon', 30);

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['email' => 'admin-lease@example.test']);
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create(['email' => 'manager-lease@example.test']);
        $manager->assignRole('manager');

        /** @var User $owner */
        $owner = User::factory()->create(['email' => 'owner-lease@example.test']);
        $owner->assignRole('owner');

        $property = Property::factory()->create();
        $property->assignManager($manager, $superAdmin);
        $property->owners()->create([
            'user_id' => $owner->id,
            'ownership_pct' => 100,
            'capital_contribution' => 500000,
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create([
            'unit_id' => $unit->id,
        ]);

        Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-01-01',
            'end_on' => '2026-05-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->artisan('phase7:dispatch-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'lease_expiring_soon',
            'status' => 'sent',
            'recipient_email' => 'manager-lease@example.test',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'lease_expiring_soon',
            'status' => 'sent',
            'recipient_email' => 'admin-lease@example.test',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'lease_expiring_soon',
            'status' => 'sent',
            'recipient_email' => 'owner-lease@example.test',
        ]);

        Carbon::setTestNow();
    }

    public function test_dispatch_command_creates_delivery_logs_for_kyc_pending_event(): void
    {
        $this->configureOnlyEvent('kyc_pending', 0);

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['email' => 'admin-kyc@example.test']);
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create(['email' => 'manager-kyc@example.test']);
        $manager->assignRole('manager');

        /** @var User $tenantUser */
        $tenantUser = User::factory()->create(['email' => 'tenant-kyc@example.test']);
        $tenantUser->assignRole('tenant');

        $property = Property::factory()->create();
        $property->assignManager($manager, $superAdmin);

        $unit = Unit::factory()->for($property)->create();
        Tenant::factory()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenantUser->id,
            'kyc_status' => 'pending',
        ]);

        $this->artisan('phase7:dispatch-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'kyc_pending',
            'status' => 'sent',
            'recipient_email' => 'tenant-kyc@example.test',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'kyc_pending',
            'status' => 'sent',
            'recipient_email' => 'manager-kyc@example.test',
        ]);
    }

    public function test_dispatch_command_creates_delivery_logs_for_emi_due_event(): void
    {
        Carbon::setTestNow('2026-05-01 09:00:00');
        $this->configureOnlyEvent('emi_due_reminder', 3);

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['email' => 'admin-emi@example.test']);
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create();

        PropertyLoan::query()->create([
            'property_id' => $property->id,
            'lender_name' => 'EMI Lender',
            'loan_amount' => 1000000,
            'interest_rate' => 8,
            'interest_rate_type' => 'fixed',
            'loan_start_date' => '2026-01-01',
            'tenure_months' => 120,
            'emi_amount' => 15000,
            'emi_due_day' => 4,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->artisan('phase7:dispatch-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'emi_due_reminder',
            'status' => 'sent',
            'recipient_email' => 'admin-emi@example.test',
        ]);

        Carbon::setTestNow();
    }

    public function test_dispatch_command_creates_delivery_logs_for_deposit_pending_and_refund_overdue_events(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['email' => 'admin-deposit@example.test']);
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create(['email' => 'manager-deposit@example.test']);
        $manager->assignRole('manager');

        $property = Property::factory()->create();
        $property->assignManager($manager, $superAdmin);
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);

        $collectionPendingLease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'start_on' => '2026-05-01',
            'end_on' => '2026-12-31',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $refundOverdueLease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'terminated',
            'start_on' => '2026-01-01',
            'end_on' => '2026-04-30',
            'terminated_at' => '2026-05-01 10:00:00',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $refundDeposit = LeaseDeposit::factory()->create([
            'lease_id' => $refundOverdueLease->id,
            'expected_amount' => 25000,
            'current_balance' => 5000,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $refundDeposit->entries()->create([
            'entry_type' => 'collection',
            'amount' => 5000,
            'created_by' => $superAdmin->id,
            'occurred_at' => now()->subDays(15),
        ]);

        $this->configureOnlyEvent('deposit_collection_pending', 7);

        $this->artisan('phase7:dispatch-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'deposit_collection_pending',
            'status' => 'sent',
            'recipient_email' => 'manager-deposit@example.test',
        ]);

        $this->configureOnlyEvent('deposit_refund_overdue', 14);

        $this->artisan('phase7:dispatch-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'deposit_refund_overdue',
            'status' => 'sent',
            'recipient_email' => 'admin-deposit@example.test',
        ]);

        Carbon::setTestNow();
    }

    public function test_dispatch_command_creates_delivery_logs_for_rent_return_pending_settlement_overdue_event(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $this->configureOnlyEvent('rent_return_pending_settlement_overdue', 7);

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['email' => 'admin-return@example.test']);
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create(['email' => 'manager-return@example.test']);
        $manager->assignRole('manager');

        $property = Property::factory()->create();
        $property->assignManager($manager, $superAdmin);
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->create(['unit_id' => $unit->id]);

        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'status' => 'terminated',
            'start_on' => '2026-01-01',
            'end_on' => '2026-04-30',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
            'terminated_at' => '2026-05-01 09:00:00',
        ]);

        $rentReturn = RentReturn::query()->create([
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'unit_id' => $lease->unit_id,
            'property_id' => $lease->unit->property_id,
            'vacation_date' => '2026-05-01',
            'last_paid_through_date' => '2026-05-31',
            'billing_month' => '2026-05-01',
            'daily_rate' => 300,
            'unused_days' => 15,
            'suggested_amount' => 4500,
            'status' => 'pending_settlement',
            'settlement_method' => null,
            'settlement_amount' => null,
            'ledger_posted' => false,
            'initiated_by' => $superAdmin->id,
            'initiated_at' => now()->subDays(10),
            'processed_by' => null,
            'processed_at' => null,
        ]);

        $rentReturn->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->save();

        $this->artisan('phase7:dispatch-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'rent_return_pending_settlement_overdue',
            'status' => 'sent',
            'recipient_email' => 'manager-return@example.test',
        ]);

        Carbon::setTestNow();
    }

    private function configureOnlyEvent(string $eventKey, int $leadDays): void
    {
        NotificationEventSetting::query()->delete();

        foreach (ReminderNotificationService::EVENTS as $key => $defaultLeadDays) {
            NotificationEventSetting::query()->create([
                'event_key' => $key,
                'is_enabled' => $key === $eventKey,
                'lead_days' => $key === $eventKey ? $leadDays : $defaultLeadDays,
            ]);
        }
    }
}
