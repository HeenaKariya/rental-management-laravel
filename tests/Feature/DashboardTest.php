<?php

namespace Tests\Feature;

use App\Models\AuthAuditLog;
use App\Models\Invitation;
use App\Models\Property;
use App\Models\Role;
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
}