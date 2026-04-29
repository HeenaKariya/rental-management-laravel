<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Contracts\WhatsappOtpGateway;
use App\Domain\Auth\Notifications\TwoFactorOtpNotification;
use App\Domain\Auth\Services\TwoFactorOtpBroker;
use App\Models\AuthAuditLog;
use App\Models\PreSession;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->app->instance(WhatsappOtpGateway::class, new class implements WhatsappOtpGateway
        {
            public function send(string $phone, string $message): void {}
        });
    }

    public function test_security_settings_page_shows_recent_auth_activity(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('tenant');

        AuthAuditLog::query()->create([
            'user_id' => $user->id,
            'event' => 'two_factor.confirmed',
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('settings.security'))
            ->assertOk()
            ->assertSee('Manage two-factor authentication.')
            ->assertSee('Recent authentication events')
            ->assertSee('2FA confirmed');
    }

    public function test_fortify_two_factor_events_are_written_to_the_auth_audit_log(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Event::dispatch(new TwoFactorAuthenticationChallenged($user));
        Event::dispatch(new TwoFactorAuthenticationEnabled($user));
        Event::dispatch(new TwoFactorAuthenticationConfirmed($user));
        Event::dispatch(new TwoFactorAuthenticationDisabled($user));
        Event::dispatch(new RecoveryCodeReplaced($user, 'backup-code-1234'));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => 'two_factor.challenged',
        ]);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => 'two_factor.confirmed',
        ]);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => 'two_factor.recovery_code_used',
        ]);
    }

    public function test_completing_a_pre_session_records_a_two_factor_passed_audit_entry(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $preSession = PreSession::query()->create([
            'user_id' => $user->id,
            'token' => 'phase-1-pre-session-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        Route::middleware('web')->get('/test-phase-1-two-factor-pass', function () use ($user, $preSession) {
            request()->session()->put('auth.pre_session_token', $preSession->token);

            event(new Login('web', $user, false));

            return response()->noContent();
        });

        $this->get('/test-phase-1-two-factor-pass')->assertNoContent();

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => 'two_factor.passed',
        ]);

        $this->assertNotNull($preSession->fresh()->completed_at);
    }

    public function test_super_admin_can_view_two_factor_oversight(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
        ]);
        $superAdmin->assignRole('super_admin');

        /** @var User $tenant */
        $tenant = User::factory()->create([
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
        ]);
        $tenant->assignRole('tenant');
        $tenant->forceFill([
            'two_factor_secret' => encrypt('tenant-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['tenant-code-1', 'tenant-code-2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        AuthAuditLog::record($tenant, 'two_factor.confirmed');

        $this->actingAs($superAdmin)
            ->get(route('admin.security.two-factor.index'))
            ->assertOk()
            ->assertSee('Monitor two-factor adoption and recent auth activity.')
            ->assertSee('Tenant User')
            ->assertSee('tenant@example.com')
            ->assertSee('Confirmed')
            ->assertSee('2FA confirmed');
    }

    public function test_non_super_admin_cannot_view_two_factor_oversight(): void
    {
        /** @var User $tenant */
        $tenant = User::factory()->create();
        $tenant->assignRole('tenant');

        $this->actingAs($tenant)
            ->get(route('admin.security.two-factor.index'))
            ->assertForbidden();
    }

    public function test_locked_user_cannot_submit_login_form(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'password' => 'Password123!',
        ]);
        $user->forceFill([
            'auth_soft_locked_until' => now()->addMinutes(User::SOFT_LOCK_MINUTES),
        ])->save();

        $this->post(route('login.store'), [
            'email' => 'locked@example.com',
            'password' => 'Password123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failed_login_events_trigger_soft_then_hard_locks(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        for ($cycle = 0; $cycle < User::HARD_LOCK_THRESHOLD; $cycle++) {
            for ($attempt = 0; $attempt < User::PRIMARY_AUTH_SOFT_LOCK_THRESHOLD; $attempt++) {
                Event::dispatch(new Failed('web', $user, ['email' => $user->email]));
            }

            if ($cycle < User::HARD_LOCK_THRESHOLD - 1) {
                $this->assertNotNull($user->fresh()->auth_soft_locked_until);
                $this->travel(User::SOFT_LOCK_MINUTES + 1)->minutes();
                $user->refresh()->clearExpiredSoftLock();
            }
        }

        $user->refresh();

        $this->assertNotNull($user->auth_hard_locked_at);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.lock.hard',
        ]);
    }

    public function test_locked_two_factor_challenge_redirects_back_to_login(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->forceFill([
            'auth_soft_locked_until' => now()->addMinutes(User::SOFT_LOCK_MINUTES),
            'two_factor_secret' => encrypt('tenant-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['tenant-code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->get(route('two-factor.login'))
            ->assertRedirect(route('login'));
    }

    public function test_repeated_two_factor_failures_trigger_a_soft_lock_and_surface_in_admin_oversight(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $tenant */
        $tenant = User::factory()->create([
            'name' => 'Lock Target',
            'email' => 'lock-target@example.com',
        ]);
        $tenant->assignRole('tenant');

        for ($attempt = 0; $attempt < User::TWO_FACTOR_SOFT_LOCK_THRESHOLD; $attempt++) {
            Event::dispatch(new TwoFactorAuthenticationFailed($tenant));
        }

        $tenant->refresh();

        $this->assertNotNull($tenant->auth_soft_locked_until);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $tenant->id,
            'event' => 'auth.lock.soft',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.security.two-factor.index'))
            ->assertOk()
            ->assertSee('Soft Locked')
            ->assertSee('Lock Target')
            ->assertSee('Temporarily locked');
    }

    public function test_super_admin_can_release_a_hard_lock(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'name' => 'Root Admin',
        ]);
        $superAdmin->assignRole('super_admin');

        /** @var User $lockedUser */
        $lockedUser = User::factory()->create([
            'email' => 'hard-locked@example.com',
        ]);
        $lockedUser->assignRole('tenant');
        $lockedUser->forceFill([
            'auth_hard_locked_at' => now(),
            'auth_soft_lock_count' => User::HARD_LOCK_THRESHOLD,
            'login_failed_attempts' => 3,
            'two_factor_failed_attempts' => 2,
        ])->save();

        $this->actingAs($superAdmin)
            ->post(route('admin.security.two-factor.release-lock', $lockedUser))
            ->assertRedirect(route('admin.security.two-factor.index'));

        $lockedUser->refresh();

        $this->assertNull($lockedUser->auth_hard_locked_at);
        $this->assertSame(0, $lockedUser->auth_soft_lock_count);
        $this->assertSame(0, $lockedUser->login_failed_attempts);
        $this->assertSame(0, $lockedUser->two_factor_failed_attempts);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $lockedUser->id,
            'event' => 'auth.lock.released',
        ]);
    }

    public function test_non_super_admin_cannot_release_a_lock_from_admin_oversight(): void
    {
        /** @var User $tenant */
        $tenant = User::factory()->create();
        $tenant->assignRole('tenant');

        /** @var User $lockedUser */
        $lockedUser = User::factory()->create();
        $lockedUser->assignRole('manager');
        $lockedUser->forceFill([
            'auth_hard_locked_at' => now(),
        ])->save();

        $this->actingAs($tenant)
            ->post(route('admin.security.two-factor.release-lock', $lockedUser))
            ->assertForbidden();
    }

    public function test_super_admin_can_reset_two_factor_and_release_related_locks(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'name' => 'Root Admin',
        ]);
        $superAdmin->assignRole('super_admin');

        /** @var User $managedUser */
        $managedUser = User::factory()->create([
            'email' => 'needs-reset@example.com',
        ]);
        $managedUser->assignRole('manager');
        $managedUser->forceFill([
            'two_factor_secret' => encrypt('managed-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-a', 'code-b'])),
            'two_factor_confirmed_at' => now(),
            'auth_soft_locked_until' => now()->addMinutes(User::SOFT_LOCK_MINUTES),
            'two_factor_failed_attempts' => 4,
        ])->save();

        $this->actingAs($superAdmin)
            ->post(route('admin.security.two-factor.reset', $managedUser))
            ->assertRedirect(route('admin.security.two-factor.index'));

        $managedUser->refresh();

        $this->assertNull($managedUser->two_factor_secret);
        $this->assertNull($managedUser->two_factor_recovery_codes);
        $this->assertNull($managedUser->two_factor_confirmed_at);
        $this->assertNull($managedUser->auth_soft_locked_until);
        $this->assertSame(0, $managedUser->two_factor_failed_attempts);

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $managedUser->id,
            'event' => 'two_factor.admin_reset',
        ]);
    }

    public function test_non_super_admin_cannot_reset_two_factor_from_admin_oversight(): void
    {
        /** @var User $tenant */
        $tenant = User::factory()->create();
        $tenant->assignRole('tenant');

        /** @var User $managedUser */
        $managedUser = User::factory()->create();
        $managedUser->assignRole('manager');
        $managedUser->forceFill([
            'two_factor_secret' => encrypt('managed-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($tenant)
            ->post(route('admin.security.two-factor.reset', $managedUser))
            ->assertForbidden();
    }

    public function test_manager_two_factor_setup_sends_delivered_otp(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create([
            'email' => 'setup-manager@example.com',
        ]);
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->post(route('settings.security.two-factor.enable'))
            ->assertRedirect(route('settings.security'));

        $manager->refresh();

        $this->assertNotNull($manager->two_factor_secret);
        $this->assertNull($manager->two_factor_confirmed_at);
        Notification::assertSentTo($manager, TwoFactorOtpNotification::class);
        $this->assertDatabaseHas('two_factor_otp_tokens', [
            'user_id' => $manager->id,
            'purpose' => TwoFactorOtpBroker::PURPOSE_SETUP_CONFIRMATION,
        ]);
    }

    public function test_invalid_delivered_otp_does_not_confirm_two_factor_setup(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create([
            'email' => 'confirm-manager@example.com',
        ]);
        $manager->assignRole('manager');
        $manager->forceFill([
            'two_factor_secret' => encrypt('otp:setup-secret'),
        ])->save();

        app(TwoFactorOtpBroker::class)->dispatch($manager, TwoFactorOtpBroker::PURPOSE_SETUP_CONFIRMATION);

        $this->actingAs($manager)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->post(route('settings.security.two-factor.confirm'), [
                'code' => '000000',
            ])->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', ['code']);

        $this->assertNull($manager->fresh()->two_factor_confirmed_at);
    }

    public function test_setup_otp_resend_is_rate_limited(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create([
            'email' => 'setup-limit@example.com',
        ]);
        $manager->assignRole('manager');
        $manager->forceFill([
            'two_factor_secret' => encrypt('otp:setup-secret'),
        ])->save();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($manager)
                ->withSession(['auth.password_confirmed_at' => now()->unix()])
                ->post(route('settings.security.two-factor.otp.resend'), [
                    'channel' => 'email',
                ])->assertSessionHasNoErrors();
        }

        $this->actingAs($manager)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->post(route('settings.security.two-factor.otp.resend'), [
                'channel' => 'email',
            ])->assertSessionHasErrors('code');
    }

    public function test_manager_two_factor_challenge_sends_a_delivered_otp(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create([
            'email' => 'manager@example.com',
        ]);
        $manager->assignRole('manager');
        $manager->forceFill([
            'two_factor_secret' => encrypt('otp:manager-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession([
            'login.id' => $manager->id,
            'login.remember' => false,
        ])->get(route('two-factor.login'))
            ->assertOk()
            ->assertSee('One-time password')
            ->assertSee('Resend via Email');

        Notification::assertSentTo($manager, TwoFactorOtpNotification::class);
        $this->assertDatabaseHas('two_factor_otp_tokens', [
            'user_id' => $manager->id,
            'purpose' => TwoFactorOtpBroker::PURPOSE_LOGIN_CHALLENGE,
            'channel' => 'email',
        ]);
    }

    public function test_manager_can_complete_two_factor_challenge_with_delivered_otp(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create([
            'email' => 'manager2@example.com',
        ]);
        $manager->assignRole('manager');
        $manager->forceFill([
            'two_factor_secret' => encrypt('otp:manager-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $broker = app(TwoFactorOtpBroker::class);
        $broker->dispatch($manager, TwoFactorOtpBroker::PURPOSE_LOGIN_CHALLENGE);

        Notification::assertSentTo($manager, TwoFactorOtpNotification::class, function (TwoFactorOtpNotification $notification, array $channels) use ($manager) {
            $mailMessage = $notification->toMail($manager);

            preg_match('/\b(\d{6})\b/', implode(' ', $mailMessage->introLines), $matches);
            $code = $matches[1] ?? null;

            if (! $code) {
                return false;
            }

            session(['login.id' => $manager->id, 'login.remember' => false]);

            return $this->withSession([
                'login.id' => $manager->id,
                'login.remember' => false,
            ])->post(route('two-factor.login.store'), [
                'code' => $code,
            ])->isRedirect(route('dashboard'));
        });
    }

    public function test_otp_resend_is_rate_limited(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create([
            'email' => 'limit@example.com',
        ]);
        $manager->assignRole('manager');
        $manager->forceFill([
            'two_factor_secret' => encrypt('otp:manager-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->withSession([
                'login.id' => $manager->id,
                'login.remember' => false,
            ])->post(route('two-factor.otp.resend'), [
                'channel' => 'email',
            ])->assertSessionHasNoErrors();
        }

        $this->withSession([
            'login.id' => $manager->id,
            'login.remember' => false,
        ])->post(route('two-factor.otp.resend'), [
            'channel' => 'email',
        ])->assertSessionHasErrors('code');
    }

    public function test_whatsapp_delivery_falls_back_to_email_when_gateway_fails(): void
    {
        $this->app->instance(WhatsappOtpGateway::class, new class implements WhatsappOtpGateway
        {
            public function send(string $phone, string $message): void
            {
                throw new \RuntimeException('gateway down');
            }
        });

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'email' => 'fallback@example.com',
            'phone' => '+15550001111',
        ]);
        $superAdmin->assignRole('super_admin');
        $superAdmin->forceFill([
            'two_factor_secret' => encrypt('otp:admin-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession([
            'login.id' => $superAdmin->id,
            'login.remember' => false,
        ])->get(route('two-factor.login'))
            ->assertOk()
            ->assertSee('Fallback from WhatsApp was used.', false);

        Notification::assertSentTo($superAdmin, TwoFactorOtpNotification::class);
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $superAdmin->id,
            'event' => 'two_factor.otp_fallback',
        ]);
    }

    public function test_two_factor_otp_resend_without_a_challenged_user_redirects_to_login(): void
    {
        $this->post(route('two-factor.otp.resend'), [
            'channel' => 'email',
        ])->assertRedirect(route('login'));
    }

    public function test_locked_user_cannot_resend_two_factor_otp_and_attempt_is_audited_on_two_factor_surface(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create([
            'email' => 'resend-lock@example.com',
        ]);
        $manager->assignRole('manager');
        $manager->forceFill([
            'auth_hard_locked_at' => now(),
            'two_factor_secret' => encrypt('otp:manager-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession([
            'login.id' => $manager->id,
            'login.remember' => false,
        ])->post(route('two-factor.otp.resend'), [
            'channel' => 'email',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $manager->id,
            'event' => 'auth.lock.blocked',
            'context->surface' => 'two_factor',
        ]);
    }

    public function test_missing_two_factor_session_on_post_redirects_back_to_login(): void
    {
        $this->post(route('two-factor.login.store'), [
            'code' => '123456',
        ])->assertRedirect(route('login'));
    }

    public function test_recovery_code_is_single_use_and_is_replaced_after_successful_login(): void
    {
        /** @var User $tenant */
        $tenant = User::factory()->create([
            'email' => 'recovery@example.com',
        ]);
        $tenant->assignRole('tenant');
        $tenant->forceFill([
            'two_factor_secret' => encrypt('tenant-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recover-1111', 'recover-2222'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession([
            'login.id' => $tenant->id,
            'login.remember' => false,
        ])->post(route('two-factor.login.store'), [
            'recovery_code' => 'recover-1111',
        ])->assertRedirect(route('dashboard'));

        $tenant->refresh();

        $this->assertSame(2, $tenant->remainingRecoveryCodesCount());
        $this->assertNotContains('recover-1111', $tenant->recoveryCodes());

        $this->post(route('logout'));

        $this->withSession([
            'login.id' => $tenant->id,
            'login.remember' => false,
        ])->post(route('two-factor.login.store'), [
            'recovery_code' => 'recover-1111',
        ])->assertSessionHasErrors('recovery_code');
    }

    public function test_regenerating_recovery_codes_invalidates_the_previous_set(): void
    {
        /** @var User $tenant */
        $tenant = User::factory()->create([
            'email' => 'regen@example.com',
        ]);
        $tenant->assignRole('tenant');
        $tenant->forceFill([
            'two_factor_secret' => encrypt('tenant-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['legacy-1111', 'legacy-2222'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($tenant)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->post(route('settings.security.two-factor.recovery-codes'))
            ->assertRedirect(route('settings.security'));

        $tenant->refresh();

        $this->assertSame(8, $tenant->remainingRecoveryCodesCount());
        $this->assertNotContains('legacy-1111', $tenant->recoveryCodes());

        $this->post(route('logout'));

        $this->withSession([
            'login.id' => $tenant->id,
            'login.remember' => false,
        ])->post(route('two-factor.login.store'), [
            'recovery_code' => 'legacy-1111',
        ])->assertSessionHasErrors('recovery_code');
    }

    public function test_security_settings_warns_when_recovery_codes_are_running_low(): void
    {
        /** @var User $tenant */
        $tenant = User::factory()->create();
        $tenant->assignRole('tenant');
        $tenant->forceFill([
            'two_factor_secret' => encrypt('tenant-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['only-1111', 'only-2222'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($tenant)
            ->get(route('settings.security'))
            ->assertOk()
            ->assertSee('Only 2 recovery codes remain. Regenerate a fresh set before you run out.');
    }
}
