<?php

namespace Tests\Feature\Finance;

use App\Models\Lease;
use App\Models\NotificationDelivery;
use App\Models\Property;
use App\Models\RentLedger;
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
}
