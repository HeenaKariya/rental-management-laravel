<?php

namespace Tests\Feature\Auth;

use App\Models\AuthAuditLog;
use App\Models\PreSession;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
}
