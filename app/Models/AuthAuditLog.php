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
            'two_factor.confirmed', 'two_factor.passed' => 'badge-green',
            'two_factor.challenged' => 'badge-sky',
            'two_factor.enabled', 'two_factor.recovery_codes_regenerated' => 'badge-gold',
            'two_factor.recovery_code_used' => 'badge-coral',
            default => 'badge-outline',
        };
    }

    public function summary(): ?string
    {
        return match ($this->event) {
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
