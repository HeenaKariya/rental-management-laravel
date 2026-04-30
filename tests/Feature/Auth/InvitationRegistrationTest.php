<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Notifications\InvitationIssuedNotification;
use App\Domain\Notifications\Contracts\WhatsappNotificationGateway;
use App\Models\Invitation;
use App\Models\NotificationEventSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        Notification::fake();

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        NotificationEventSetting::query()->updateOrCreate(
            ['event_key' => 'user_invitation_issued'],
            ['is_enabled' => true, 'email_enabled' => true, 'whatsapp_enabled' => false, 'lead_days' => 0],
        );

        $this->actingAs($user)
            ->post('/admin/invitations', [
                'email' => 'manager@example.com',
                'role' => 'manager',
            ])
            ->assertRedirect(route('invitations.create'));

        $this->assertDatabaseHas('invitations', [
            'email' => 'manager@example.com',
        ]);

        Notification::assertSentOnDemand(InvitationIssuedNotification::class);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'user_invitation_issued',
            'status' => 'sent',
            'channel' => 'email',
            'recipient_email' => 'manager@example.com',
        ]);
    }

    public function test_invitation_can_send_optional_whatsapp_notification_when_phone_is_provided(): void
    {
        Notification::fake();

        $this->app->instance(WhatsappNotificationGateway::class, new class implements WhatsappNotificationGateway
        {
            public function send(string $phone, string $message): void {}
        });

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        NotificationEventSetting::query()->updateOrCreate(
            ['event_key' => 'user_invitation_issued'],
            ['is_enabled' => true, 'email_enabled' => true, 'whatsapp_enabled' => true, 'lead_days' => 0],
        );

        $this->actingAs($user)
            ->post('/admin/invitations', [
                'email' => 'manager-whatsapp@example.com',
                'phone' => '+15550009999',
                'role' => 'manager',
            ])
            ->assertRedirect(route('invitations.create'));

        $this->assertDatabaseHas('invitations', [
            'email' => 'manager-whatsapp@example.com',
            'phone' => '+15550009999',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'user_invitation_issued',
            'status' => 'sent',
            'channel' => 'email',
            'recipient_email' => 'manager-whatsapp@example.com',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'user_invitation_issued',
            'status' => 'sent',
            'channel' => 'whatsapp',
            'recipient_email' => '+15550009999',
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

    public function test_expired_invitation_cannot_be_used_for_registration(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $tenantRoleId = Role::query()->where('slug', 'tenant')->value('id');

        $invitation = Invitation::issue([
            'email' => 'expired@example.com',
            'role_id' => $tenantRoleId,
            'invited_by' => $superAdmin->id,
        ]);

        $invitation->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        $this->post('/register', [
            'name' => 'Expired User',
            'email' => 'expired@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_token' => $invitation->token,
        ])->assertSessionHasErrors('invitation_token');

        $this->assertDatabaseMissing('users', [
            'email' => 'expired@example.com',
        ]);
    }

    public function test_registration_email_must_match_the_invitation_email(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $managerRoleId = Role::query()->where('slug', 'manager')->value('id');

        $invitation = Invitation::issue([
            'email' => 'manager-invite@example.com',
            'role_id' => $managerRoleId,
            'invited_by' => $superAdmin->id,
        ]);

        $this->post('/register', [
            'name' => 'Wrong Email User',
            'email' => 'other@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_token' => $invitation->token,
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', [
            'email' => 'other@example.com',
        ]);
    }

    public function test_accepted_invitation_cannot_be_reused(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $ownerRoleId = Role::query()->where('slug', 'owner')->value('id');

        $invitation = Invitation::issue([
            'email' => 'used@example.com',
            'role_id' => $ownerRoleId,
            'invited_by' => $superAdmin->id,
        ]);

        $this->post('/register', [
            'name' => 'Used Invite User',
            'email' => 'used@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_token' => $invitation->token,
        ])->assertRedirect('/dashboard');

        $this->post(route('logout'));

        $this->post('/register', [
            'name' => 'Second Use User',
            'email' => 'used@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invitation_token' => $invitation->token,
        ])->assertSessionHasErrors(['invitation_token', 'email']);
    }

    public function test_register_page_shows_warning_for_invalid_or_used_invitation_link(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $tenantRoleId = Role::query()->where('slug', 'tenant')->value('id');

        $invitation = Invitation::issue([
            'email' => 'invalid-link@example.com',
            'role_id' => $tenantRoleId,
            'invited_by' => $superAdmin->id,
        ]);

        $invitation->markAccepted();

        $this->get(route('register', ['invite' => $invitation->token]))
            ->assertOk()
            ->assertSee('A valid invitation link is required to create an account.');
    }
}
