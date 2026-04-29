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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
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
}
