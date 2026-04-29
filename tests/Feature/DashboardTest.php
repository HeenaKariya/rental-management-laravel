<?php

namespace Tests\Feature;

use App\Models\AuthAuditLog;
use App\Models\Invitation;
use App\Models\Lease;
use App\Models\LeaseDeposit;
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
}