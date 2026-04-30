<?php

namespace Tests\Feature\Admin;

use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_notification_center_and_update_event_settings(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Notification center')
            ->assertSee('Trigger configuration');

        $this->actingAs($superAdmin)
            ->put(route('admin.notifications.settings.update'), [
                'events' => [
                    'rent_due_reminder' => ['is_enabled' => '1', 'lead_days' => 5],
                    'lease_expiring_soon' => ['is_enabled' => '0', 'lead_days' => 10],
                ],
            ])
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseHas('notification_event_settings', [
            'event_key' => 'rent_due_reminder',
            'is_enabled' => 1,
            'lead_days' => 5,
        ]);

        $this->assertDatabaseHas('notification_event_settings', [
            'event_key' => 'lease_expiring_soon',
            'is_enabled' => 0,
            'lead_days' => 10,
        ]);
    }

    public function test_super_admin_can_retry_a_single_failed_delivery_from_admin_panel(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $recipient */
        $recipient = User::factory()->create(['email' => 'recipient@example.test']);

        $delivery = NotificationDelivery::query()->create([
            'event_key' => 'rent_due_reminder',
            'notifiable_type' => User::class,
            'notifiable_id' => $recipient->id,
            'recipient_email' => null,
            'channel' => 'email',
            'status' => 'failed',
            'subject' => 'Rent due reminder',
            'message_preview' => 'Rent is due soon.',
            'failure_reason' => 'Recipient email is missing for this notification.',
            'failed_at' => now(),
            'retry_count' => 1,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.notifications.retry-one', $delivery))
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $delivery->id,
            'status' => 'sent',
            'recipient_email' => 'recipient@example.test',
            'retry_count' => 2,
        ]);
    }

    public function test_non_super_admin_cannot_access_notification_center_routes(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->get(route('admin.notifications.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_filter_delivery_logs_and_export_csv(): void
    {
        Carbon::setTestNow('2026-04-30 10:00:00');

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        NotificationDelivery::query()->create([
            'event_key' => 'rent_due_reminder',
            'recipient_email' => 'alpha@example.test',
            'channel' => 'email',
            'status' => 'sent',
            'subject' => 'Rent due reminder',
            'message_preview' => 'A',
            'sent_at' => now(),
        ]);

        NotificationDelivery::query()->create([
            'event_key' => 'lease_expiring_soon',
            'recipient_email' => 'beta@example.test',
            'channel' => 'email',
            'status' => 'failed',
            'subject' => 'Lease expiring soon',
            'message_preview' => 'B',
            'failed_at' => now(),
            'failure_reason' => 'Mailbox unavailable',
        ]);

        Carbon::setTestNow(now()->subDays(10));

        NotificationDelivery::query()->create([
            'event_key' => 'lease_expiring_soon',
            'recipient_email' => 'stale@example.test',
            'channel' => 'email',
            'status' => 'failed',
            'subject' => 'Lease expiring soon',
            'message_preview' => 'Old',
            'failed_at' => now()->subDays(10),
            'failure_reason' => 'Old failure',
        ]);

        Carbon::setTestNow('2026-04-30 10:00:00');

        $query = [
            'status' => 'failed',
            'event_key' => 'lease_expiring_soon',
            'recipient' => 'beta@',
        ];

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.index', $query))
            ->assertOk()
            ->assertSee('Failed only')
            ->assertSee('Today')
            ->assertSee('Last 7 days')
            ->assertSee('Recent failures')
            ->assertSee('beta@example.test')
            ->assertDontSee('alpha@example.test');

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.index', [
                'status' => 'failed',
                'date_from' => now()->subDays(6)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('beta@example.test')
            ->assertDontSee('stale@example.test');

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.export.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        Carbon::setTestNow();
    }

    public function test_notification_filters_stay_sticky_until_reset(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        NotificationDelivery::query()->create([
            'event_key' => 'rent_due_reminder',
            'recipient_email' => 'visible@example.test',
            'channel' => 'email',
            'status' => 'failed',
            'subject' => 'Fail one',
            'message_preview' => 'X',
            'failed_at' => now(),
        ]);

        NotificationDelivery::query()->create([
            'event_key' => 'rent_due_reminder',
            'recipient_email' => 'hidden@example.test',
            'channel' => 'email',
            'status' => 'sent',
            'subject' => 'Sent one',
            'message_preview' => 'Y',
            'sent_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('visible@example.test')
            ->assertDontSee('hidden@example.test');

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('visible@example.test')
            ->assertDontSee('hidden@example.test');

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.index', ['reset' => 1]))
            ->assertRedirect(route('admin.notifications.index'));

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('visible@example.test')
            ->assertSee('hidden@example.test');
    }
}
