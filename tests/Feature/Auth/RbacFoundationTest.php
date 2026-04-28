<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Invitation;
use App\Models\PreSession;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_invited_users_can_receive_the_tenant_role(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $tenantRoleId = Role::query()->where('slug', 'tenant')->value('id');

        $invitation = Invitation::issue([
            'email' => 'tenant@example.com',
            'role_id' => $tenantRoleId,
            'invited_by' => $superAdmin->id,
        ]);

        $user = app(CreateNewUser::class)->create([
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_token' => $invitation->token,
        ]);

        $this->assertTrue($user->fresh()->hasRole('tenant'));
    }

    public function test_guest_users_are_redirected_away_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_role_middleware_blocks_users_without_the_required_role(): void
    {
        Route::middleware(['web', 'auth', 'role:super_admin'])->get('/test-admin-area', fn () => 'ok');

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('tenant');

        $this->actingAs($user)
            ->get('/test-admin-area')
            ->assertForbidden();
    }

    public function test_gate_allows_super_admin_access(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->assertTrue(Gate::forUser($user)->allows('access-super-admin-panel'));
    }

    public function test_two_factor_challenge_creates_a_pre_session_with_a_fifteen_minute_ttl(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->get('/two-factor-challenge')->assertOk();

        $preSession = PreSession::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($preSession);
        $this->assertTrue($preSession->expires_at->between(now()->addMinutes(14), now()->addMinutes(15)));
    }

    public function test_dashboard_is_not_accessible_while_a_pre_session_token_is_present(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('tenant');

        $preSession = PreSession::query()->create([
            'user_id' => $user->id,
            'token' => 'test-pre-session-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->actingAs($user)
            ->withSession(['auth.pre_session_token' => $preSession->token])
            ->get('/dashboard')
            ->assertRedirect('/login');
    }
}
