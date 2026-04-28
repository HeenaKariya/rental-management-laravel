<?php

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_requires_a_valid_invitation(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('A valid invitation link is required to create an account.');
    }

    public function test_super_admin_can_create_a_role_scoped_invitation(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->post('/admin/invitations', [
                'email' => 'manager@example.com',
                'role' => 'manager',
            ])
            ->assertRedirect(route('invitations.create'));

        $this->assertDatabaseHas('invitations', [
            'email' => 'manager@example.com',
        ]);
    }

    public function test_non_super_admin_cannot_create_invitations(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('tenant');

        $this->actingAs($user)
            ->get('/admin/invitations/create')
            ->assertForbidden();
    }

    public function test_invited_user_receives_the_invited_role_on_registration(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $ownerRoleId = Role::query()->where('slug', 'owner')->value('id');

        $invitation = Invitation::issue([
            'email' => 'owner@example.com',
            'role_id' => $ownerRoleId,
            'invited_by' => $superAdmin->id,
        ]);

        $this->post('/register', [
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_token' => $invitation->token,
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('owner'));
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }
}
