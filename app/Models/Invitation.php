<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'role_id',
        'invited_by',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function issue(array $attributes): self
    {
        return static::query()->create([
            'email' => $attributes['email'],
            'role_id' => $attributes['role_id'],
            'invited_by' => $attributes['invited_by'],
            'token' => (string) Str::uuid(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public static function validToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::query()
            ->with('role')
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function markAccepted(): void
    {
        $this->forceFill([
            'accepted_at' => now(),
        ])->save();
    }
}
