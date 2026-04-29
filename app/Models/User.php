<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const PRIMARY_AUTH_SOFT_LOCK_THRESHOLD = 5;

    public const TWO_FACTOR_SOFT_LOCK_THRESHOLD = 5;

    public const HARD_LOCK_THRESHOLD = 3;

    public const SOFT_LOCK_MINUTES = 15;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auth_hard_locked_at' => 'datetime',
            'auth_soft_locked_until' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function authAuditLogs(): HasMany
    {
        return $this->hasMany(AuthAuditLog::class);
    }

    public function latestAuthAuditLog(): HasOne
    {
        return $this->hasOne(AuthAuditLog::class)->latestOfMany('occurred_at');
    }

    public function twoFactorOtpTokens(): HasMany
    {
        return $this->hasMany(TwoFactorOtpToken::class);
    }

    public function assignRole(string $slug): void
    {
        $roleId = Role::query()->where('slug', $slug)->value('id');

        if (! $roleId) {
            return;
        }

        $this->roles()->syncWithoutDetaching([$roleId]);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->exists();
    }

    public function roleSummary(): string
    {
        $names = $this->roles->pluck('name')->filter()->values();

        return $names->isNotEmpty() ? $names->implode(', ') : 'No role assigned';
    }

    public function twoFactorStatus(): string
    {
        if ($this->hasEnabledTwoFactorAuthentication()) {
            return 'Confirmed';
        }

        if ($this->two_factor_secret !== null) {
            return 'Pending confirmation';
        }

        return 'Not enabled';
    }

    public function twoFactorStatusBadgeClass(): string
    {
        return match ($this->twoFactorStatus()) {
            'Confirmed' => 'badge-green',
            'Pending confirmation' => 'badge-gold',
            default => 'badge-outline',
        };
    }

    public function usesDeliveredOtpTwoFactor(): bool
    {
        return $this->hasAnyRole(['super_admin', 'manager']);
    }

    public function preferredOtpChannels(): array
    {
        $channels = [];

        if ($this->phone) {
            $channels[] = 'whatsapp';
        }

        if ($this->email) {
            $channels[] = 'email';
        }

        return array_values(array_unique($channels));
    }

    public function clearExpiredSoftLock(): void
    {
        if (! $this->auth_soft_locked_until?->isPast()) {
            return;
        }

        $this->forceFill([
            'auth_soft_locked_until' => null,
        ])->save();
    }

    public function isSoftLocked(): bool
    {
        return $this->auth_soft_locked_until?->isFuture() ?? false;
    }

    public function isHardLocked(): bool
    {
        return $this->auth_hard_locked_at !== null;
    }

    public function isAuthLocked(): bool
    {
        return $this->isHardLocked() || $this->isSoftLocked();
    }

    public function authLockStatus(): string
    {
        if ($this->isHardLocked()) {
            return 'Hard locked';
        }

        if ($this->isSoftLocked()) {
            return 'Temporarily locked';
        }

        return 'Open';
    }

    public function authLockStatusBadgeClass(): string
    {
        return match ($this->authLockStatus()) {
            'Hard locked' => 'badge-coral',
            'Temporarily locked' => 'badge-gold',
            default => 'badge-outline',
        };
    }

    public function activeAuthLockMessage(): ?string
    {
        if ($this->isHardLocked()) {
            return 'This account is hard locked. Contact a Super Admin to restore access.';
        }

        if ($this->isSoftLocked()) {
            return 'This account is temporarily locked until '.$this->auth_soft_locked_until?->format('M j, Y g:i A').'.';
        }

        return null;
    }

    public function recordPrimaryAuthenticationFailure(): ?string
    {
        return $this->recordAuthenticationFailure('login_failed_attempts', self::PRIMARY_AUTH_SOFT_LOCK_THRESHOLD);
    }

    public function recordTwoFactorFailure(): ?string
    {
        return $this->recordAuthenticationFailure('two_factor_failed_attempts', self::TWO_FACTOR_SOFT_LOCK_THRESHOLD);
    }

    public function clearPrimaryAuthenticationFailures(): void
    {
        if (($this->login_failed_attempts ?? 0) === 0) {
            return;
        }

        $this->forceFill([
            'login_failed_attempts' => 0,
        ])->save();
    }

    public function clearTwoFactorFailures(): void
    {
        if (($this->two_factor_failed_attempts ?? 0) === 0) {
            return;
        }

        $this->forceFill([
            'two_factor_failed_attempts' => 0,
        ])->save();
    }

    public function releaseAuthLock(): void
    {
        $this->forceFill([
            'auth_hard_locked_at' => null,
            'auth_soft_locked_until' => null,
            'auth_soft_lock_count' => 0,
            'login_failed_attempts' => 0,
            'two_factor_failed_attempts' => 0,
        ])->save();
    }

    public function adminResetTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->releaseAuthLock();
    }

    protected function recordAuthenticationFailure(string $field, int $threshold): ?string
    {
        if ($this->isHardLocked()) {
            return null;
        }

        $nextAttempts = (int) ($this->{$field} ?? 0) + 1;
        $triggeredEvent = null;

        $this->{$field} = $nextAttempts;

        if ($nextAttempts >= $threshold) {
            $softLockCount = (int) ($this->auth_soft_lock_count ?? 0) + 1;

            $this->{$field} = 0;
            $this->auth_soft_lock_count = $softLockCount;

            if ($softLockCount >= self::HARD_LOCK_THRESHOLD) {
                $this->auth_hard_locked_at = now();
                $this->auth_soft_locked_until = null;
                $triggeredEvent = 'auth.lock.hard';
            } else {
                $this->auth_soft_locked_until = now()->addMinutes(self::SOFT_LOCK_MINUTES);
                $triggeredEvent = 'auth.lock.soft';
            }
        }

        $this->save();

        return $triggeredEvent;
    }
}
