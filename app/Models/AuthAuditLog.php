<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'ip_address',
        'user_agent',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public static function record(?AuthenticatableContract $user, string $event, array $context = []): self
    {
        $request = app()->bound('request') ? request() : null;

        return static::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            'event' => $event,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->event) {
            'auth.lock.blocked' => 'Locked attempt blocked',
            'auth.lock.hard' => 'Hard lock applied',
            'auth.lock.released' => 'Lock released',
            'auth.lock.soft' => 'Temporary lock applied',
            'auth.login_failed' => 'Primary login failed',
            'auth.two_factor_failed' => '2FA challenge failed',
            'two_factor.admin_reset' => '2FA reset by admin',
            'two_factor.challenged' => '2FA challenge started',
            'two_factor.enabled' => '2FA setup started',
            'two_factor.confirmed' => '2FA confirmed',
            'two_factor.disabled' => '2FA disabled',
            'two_factor.passed' => '2FA challenge completed',
            'two_factor.recovery_code_used' => 'Recovery code used',
            'two_factor.recovery_codes_regenerated' => 'Recovery codes regenerated',
            default => str($this->event)->replace(['_', '.'], ' ')->title()->value(),
        };
    }

    public function badgeClass(): string
    {
        return match ($this->event) {
            'auth.lock.hard' => 'badge-coral',
            'auth.lock.blocked', 'auth.lock.released', 'auth.lock.soft' => 'badge-gold',
            'auth.login_failed', 'auth.two_factor_failed', 'two_factor.recovery_code_used' => 'badge-coral',
            'two_factor.admin_reset' => 'badge-violet',
            'two_factor.confirmed', 'two_factor.passed' => 'badge-green',
            'two_factor.challenged' => 'badge-sky',
            'two_factor.enabled', 'two_factor.recovery_codes_regenerated' => 'badge-gold',
            default => 'badge-outline',
        };
    }

    public function summary(): ?string
    {
        return match ($this->event) {
            'auth.lock.blocked' => ($this->context['state'] ?? null) === 'Hard locked'
                ? 'A hard-locked account attempted access.'
                : 'A temporarily locked account attempted access.',
            'auth.lock.released' => isset($this->context['actor_name'])
                ? 'Lock released by '.$this->context['actor_name'].'.'
                : 'Lock released by a Super Admin.',
            'auth.lock.soft' => 'Account locked for '.($this->context['minutes'] ?? User::SOFT_LOCK_MINUTES).' minutes after repeated failures.',
            'auth.lock.hard' => 'Account reached the repeated lock threshold and now requires Super Admin intervention.',
            'auth.login_failed' => 'Email and password verification failed.',
            'auth.two_factor_failed' => ($this->context['method'] ?? null) === 'recovery_code'
                ? 'Recovery code verification failed.'
                : 'Authenticator code verification failed.',
            'two_factor.admin_reset' => isset($this->context['actor_name'])
                ? 'Two-factor enrollment reset by '.$this->context['actor_name'].'.'
                : 'Two-factor enrollment reset by a Super Admin.',
            'two_factor.passed' => ($this->context['method'] ?? null) === 'recovery_code'
                ? 'Completed with a recovery code.'
                : 'Completed with an authenticator code.',
            'two_factor.recovery_code_used' => isset($this->context['code_suffix'])
                ? 'Consumed backup code ending in '.$this->context['code_suffix'].'.'
                : 'Consumed one backup code.',
            default => null,
        };
    }
}
