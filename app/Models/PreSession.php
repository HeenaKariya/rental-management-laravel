<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PreSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function issueForUser(int $userId): self
    {
        static::query()
            ->where('user_id', $userId)
            ->whereNull('completed_at')
            ->delete();

        return static::query()->create([
            'user_id' => $userId,
            'token' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public static function activeByToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::query()
            ->where('token', $token)
            ->whereNull('completed_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public static function completeByToken(?string $token): void
    {
        if (! $token) {
            return;
        }

        static::query()
            ->where('token', $token)
            ->whereNull('completed_at')
            ->update([
                'completed_at' => now(),
            ]);
    }
}
