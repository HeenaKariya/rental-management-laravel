<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceRequest;
use App\Models\NotificationDelivery;
use App\Models\NotificationEventSetting;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceRequestAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_tenant_can_create_and_track_own_maintenance_request(): void
    {
        /** @var User $tenantUser */
        $tenantUser = User::factory()->create(['email' => 'tenant-maint@example.test']);
        $tenantUser->assignRole('tenant');

        $property = Property::factory()->create(['title' => 'Tenant Tower']);
        $unit = Unit::factory()->create(['property_id' => $property->id, 'unit_number' => 'T-101']);
        $tenant = Tenant::factory()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenantUser->id,
            'full_name' => 'Tenant Maint',
        ]);

        $this->actingAs($tenantUser)
            ->post(route('maintenance.store'), [
                'unit_id' => $unit->id,
                'title' => 'Leaking faucet',
                'category' => 'plumbing',
                'priority' => 'high',
                'description' => 'Kitchen faucet is leaking all day.',
                'photos' => [UploadedFile::fake()->image('leak.jpg')],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('maintenance_requests', [
            'title' => 'Leaking faucet',
            'status' => 'open',
            'submitted_by' => $tenantUser->id,
            'tenant_id' => $tenant->id,
        ]);

        /** @var MaintenanceRequest $maintenanceRequest */
        $maintenanceRequest = MaintenanceRequest::query()->where('title', 'Leaking faucet')->firstOrFail();

        $this->assertTrue(Storage::disk('public')->exists($maintenanceRequest->photos()->firstOrFail()->path));

        $this->actingAs($tenantUser)
            ->get(route('maintenance.index'))
            ->assertOk()
            ->assertSee('Leaking faucet');

        $this->actingAs($tenantUser)
            ->get(route('maintenance.show', $maintenanceRequest))
            ->assertOk()
            ->assertSee('Leaking faucet')
            ->assertSee('Kitchen faucet is leaking all day.');
    }

    public function test_manager_sees_only_assigned_property_requests_and_can_update_status(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create(['email' => 'manager-maint@example.test']);
        $manager->assignRole('manager');

        $assignedProperty = Property::factory()->create(['title' => 'Assigned Complex']);
        $assignedProperty->assignManager($manager, $superAdmin);
        $assignedUnit = Unit::factory()->create(['property_id' => $assignedProperty->id]);

        /** @var User $assignedTenantUser */
        $assignedTenantUser = User::factory()->create(['email' => 'assigned-tenant-maint@example.test']);
        $assignedTenantUser->assignRole('tenant');

        $assignedTenant = Tenant::factory()->create([
            'unit_id' => $assignedUnit->id,
            'user_id' => $assignedTenantUser->id,
            'email' => $assignedTenantUser->email,
        ]);

        $assignedRequest = MaintenanceRequest::factory()->create([
            'unit_id' => $assignedUnit->id,
            'tenant_id' => $assignedTenant->id,
            'title' => 'Assigned Issue',
            'status' => 'open',
        ]);

        $hiddenUnit = Unit::factory()->create();
        $hiddenRequest = MaintenanceRequest::factory()->create([
            'unit_id' => $hiddenUnit->id,
            'title' => 'Hidden Issue',
            'status' => 'open',
        ]);

        $this->actingAs($manager)
            ->get(route('maintenance.index'))
            ->assertOk()
            ->assertSee('Assigned Issue')
            ->assertDontSee('Hidden Issue');

        $this->actingAs($manager)
            ->get(route('maintenance.show', $hiddenRequest))
            ->assertForbidden();

        $this->actingAs($manager)
            ->patch(route('maintenance.update', $assignedRequest), [
                'status' => 'in_progress',
                'internal_notes' => 'Vendor informed.',
                'vendor_name' => 'QuickFix Services',
                'repair_cost' => 850.50,
            ])
            ->assertRedirect(route('maintenance.show', $assignedRequest));

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $assignedRequest->id,
            'status' => 'in_progress',
            'vendor_name' => 'QuickFix Services',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'maintenance_request_status_changed',
            'status' => 'sent',
            'recipient_email' => 'assigned-tenant-maint@example.test',
        ]);

        $this->actingAs($manager)
            ->patch(route('maintenance.update', $assignedRequest), [
                'status' => 'closed',
                'internal_notes' => 'Completed and closed.',
            ])
            ->assertRedirect(route('maintenance.show', $assignedRequest));

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $assignedRequest->id,
            'status' => 'closed',
        ]);

        $this->actingAs($manager)
            ->patch(route('maintenance.update', $assignedRequest), [
                'status' => 'in_progress',
                'internal_notes' => 'Trying invalid reopen.',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_tenant_cannot_update_maintenance_request_status(): void
    {
        /** @var User $tenantUser */
        $tenantUser = User::factory()->create();
        $tenantUser->assignRole('tenant');

        $unit = Unit::factory()->create();
        $tenant = Tenant::factory()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenantUser->id,
        ]);

        $maintenanceRequest = MaintenanceRequest::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'submitted_by' => $tenantUser->id,
            'status' => 'open',
        ]);

        $this->actingAs($tenantUser)
            ->patch(route('maintenance.update', $maintenanceRequest), [
                'status' => 'in_progress',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $maintenanceRequest->id,
            'status' => 'open',
        ]);
    }

    public function test_maintenance_status_change_notification_is_skipped_when_event_is_disabled(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        NotificationEventSetting::query()->updateOrCreate(
            ['event_key' => 'maintenance_request_status_changed'],
            ['is_enabled' => false, 'lead_days' => 0],
        );

        $property = Property::factory()->create();
        $property->assignManager($manager, $superAdmin);
        $unit = Unit::factory()->create(['property_id' => $property->id]);

        /** @var User $tenantUser */
        $tenantUser = User::factory()->create(['email' => 'tenant-notify-off@example.test']);
        $tenantUser->assignRole('tenant');

        $tenant = Tenant::factory()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenantUser->id,
        ]);

        $maintenanceRequest = MaintenanceRequest::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'submitted_by' => $tenantUser->id,
            'status' => 'open',
        ]);

        $this->actingAs($manager)
            ->patch(route('maintenance.update', $maintenanceRequest), [
                'status' => 'in_progress',
                'internal_notes' => 'Working on it.',
            ])
            ->assertRedirect(route('maintenance.show', $maintenanceRequest));

        $this->assertSame(
            0,
            NotificationDelivery::query()->where('event_key', 'maintenance_request_status_changed')->count(),
        );
    }
}
