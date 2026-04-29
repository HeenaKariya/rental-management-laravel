<?php

namespace Tests\Feature\Auth;

use App\Models\AuthAuditLog;
use App\Models\PreSession;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
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
}
