<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationDelivery extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending',
        'sent',
        'failed',
    ];

    protected $fillable = [
        'event_key',
        'notifiable_type',
        'notifiable_id',
        'recipient_email',
        'channel',
        'status',
        'subject',
        'message_preview',
        'payload',
        'sent_at',
        'failed_at',
        'failure_reason',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
            'payload' => 'array',
            'sent_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
            'retry_count' => ((int) $this->retry_count) + 1,
        ])->save();
    }
}
