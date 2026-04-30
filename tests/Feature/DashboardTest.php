<?php

namespace Tests\Feature;

use App\Models\AuthAuditLog;
use App\Models\Invitation;
use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\PreSession;
use App\Models\Property;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_users_are_redirected_from_core_phase8_smoke_routes(): void
    {
        $property = Property::factory()->create();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->get(route('properties.index'))
            ->assertRedirect(route('login'));

        $this->get(route('finance.index'))
            ->assertRedirect(route('login'));

        $this->get(route('properties.show', $property))
            ->assertRedirect(route('login'));

        $this->get(route('admin.notifications.index'))
            ->assertRedirect(route('login'));
    }

    public function test_pre_session_token_redirects_users_from_core_phase8_smoke_routes(): void
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
            ->withSession(['auth.pre_session_token' => PreSession::issueForUser($manager->id)->token])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->actingAs($manager)
            ->withSession(['auth.pre_session_token' => PreSession::issueForUser($manager->id)->token])
            ->get(route('properties.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($manager)
            ->withSession(['auth.pre_session_token' => PreSession::issueForUser($manager->id)->token])
            ->get(route('finance.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($manager)
            ->withSession(['auth.pre_session_token' => PreSession::issueForUser($manager->id)->token])
            ->get(route('properties.show', $property))
            ->assertRedirect(route('login'));

        $this->actingAs($superAdmin)
            ->withSession(['auth.pre_session_token' => PreSession::issueForUser($superAdmin->id)->token])
            ->get(route('admin.notifications.index'))
            ->assertRedirect(route('login'));
    }

    public function test_super_admin_can_open_core_phase8_smoke_routes(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'name' => 'Smoke Super Admin',
        ]);
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create(['title' => 'Smoke Admin Property']);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('properties.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('properties.show', $property))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('finance.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.rent-collection.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.expenses.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('admin.notifications.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('properties.finance.reports.owner-statement.csv', $property))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_manager_can_open_core_operational_routes_but_not_admin_notifications(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $property = Property::factory()->create(['title' => 'Smoke Manager Property']);
        $property->assignManager($manager, $superAdmin);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('properties.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('properties.show', $property))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('finance.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('finance.reports.deposits.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('finance.reports.arrears.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('admin.notifications.index'))
            ->assertForbidden();
    }

    public function test_super_admin_dashboard_shows_app_shell_and_live_summary_cards(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin User',
        ]);
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        Property::factory()->create(['title' => 'Alpha Arcade', 'lifecycle_stage' => 'active']);
        Property::factory()->create(['title' => 'Beta Residency', 'lifecycle_stage' => 'draft']);

        $managerRoleId = Role::query()->where('slug', 'manager')->value('id');
        Invitation::issue([
            'email' => 'manager-invite@example.com',
            'role_id' => $managerRoleId,
            'invited_by' => $superAdmin->id,
        ]);

        AuthAuditLog::record($superAdmin, 'two_factor.confirmed');

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Super Admin Workspace')
            ->assertSee('Dashboard')
            ->assertSee('Properties')
            ->assertSee('Open invitations')
            ->assertSee('Alpha Arcade')
            ->assertSee('Invite manager');
    }

    public function test_tenant_dashboard_shows_only_their_tenancy_records(): void
    {
        /** @var User $tenantUser */
        $tenantUser = User::factory()->create([
            'name' => 'Aarav Tenant',
        ]);
        $tenantUser->assignRole('tenant');

        $property = Property::factory()->create(['title' => 'Harbor Point']);
        $unit = Unit::factory()->create(['property_id' => $property->id, 'unit_number' => 'B-302']);
        $tenant = Tenant::factory()->create([
            'unit_id' => $unit->id,
            'user_id' => $tenantUser->id,
            'full_name' => 'Aarav Tenant',
            'kyc_status' => 'verified',
        ]);
        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'lease_number' => 'LS-TENANT-001',
            'status' => 'active',
        ]);
        $deposit = LeaseDeposit::factory()->create([
            'lease_id' => $lease->id,
            'expected_amount' => 18000,
            'current_balance' => 18000,
            'collected_total' => 18000,
        ]);

        /** @var User $otherTenantUser */
        $otherTenantUser = User::factory()->create();
        $otherTenantUser->assignRole('tenant');
        $otherUnit = Unit::factory()->create(['unit_number' => 'C-404']);
        $otherTenant = Tenant::factory()->create([
            'unit_id' => $otherUnit->id,
            'user_id' => $otherTenantUser->id,
            'full_name' => 'Hidden Tenant',
        ]);
        $otherLease = Lease::factory()->create([
            'unit_id' => $otherUnit->id,
            'tenant_id' => $otherTenant->id,
            'lease_number' => 'LS-HIDDEN-001',
            'status' => 'active',
        ]);
        LeaseDeposit::factory()->create([
            'lease_id' => $otherLease->id,
            'expected_amount' => 9000,
            'current_balance' => 9000,
        ]);

        AuthAuditLog::record($tenantUser, 'two_factor.confirmed');

        $this->actingAs($tenantUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tenant Portal')
            ->assertSee('Your tenancy workspace')
            ->assertSee('Aarav Tenant')
            ->assertSee('Harbor Point')
            ->assertSee('LS-TENANT-001')
            ->assertSee('18,000.00')
            ->assertDontSee('Hidden Tenant')
            ->assertDontSee('LS-HIDDEN-001');

        $this->actingAs($tenantUser)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSee('Back to portal');

        $this->actingAs($tenantUser)
            ->get(route('leases.show', $lease))
            ->assertOk()
            ->assertSee('read-only mode');

        $this->actingAs($tenantUser)
            ->get(route('deposits.show', $deposit))
            ->assertOk()
            ->assertSee('read-only mode');
    }

    public function test_owner_and_tenant_personas_follow_phase8_smoke_access_matrix(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $ownedProperty = Property::factory()->create(['title' => 'Owner Smoke Property']);
        $ownedProperty->owners()->create([
            'user_id' => $owner->id,
            'ownership_pct' => 100,
            'capital_contribution' => 500000,
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $hiddenProperty = Property::factory()->create();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.show', $ownedProperty))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.owner-statement.csv', $ownedProperty))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.show', $hiddenProperty))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('finance.index'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('admin.notifications.index'))
            ->assertForbidden();

        /** @var User $tenantUser */
        $tenantUser = User::factory()->create();
        $tenantUser->assignRole('tenant');

        $tenantProperty = Property::factory()->create(['title' => 'Tenant Smoke Property']);
        $tenantUnit = Unit::factory()->create([
            'property_id' => $tenantProperty->id,
            'unit_number' => 'TS-101',
        ]);
        $tenant = Tenant::factory()->create([
            'unit_id' => $tenantUnit->id,
            'user_id' => $tenantUser->id,
            'full_name' => 'Tenant Smoke',
            'kyc_status' => 'verified',
        ]);
        $tenantLease = Lease::factory()->create([
            'unit_id' => $tenantUnit->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
        $tenantDeposit = LeaseDeposit::factory()->create([
            'lease_id' => $tenantLease->id,
            'expected_amount' => 10000,
            'current_balance' => 10000,
            'collected_total' => 10000,
        ]);

        $this->actingAs($tenantUser)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($tenantUser)
            ->get(route('tenants.show', $tenant))
            ->assertOk();

        $this->actingAs($tenantUser)
            ->get(route('leases.show', $tenantLease))
            ->assertOk();

        $this->actingAs($tenantUser)
            ->get(route('deposits.show', $tenantDeposit))
            ->assertOk();

        $this->actingAs($tenantUser)
            ->get(route('properties.index'))
            ->assertForbidden();

        $this->actingAs($tenantUser)
            ->get(route('admin.notifications.index'))
            ->assertForbidden();
    }
}