<?php

namespace App\Domain\Notifications;

use App\Models\NotificationDelivery;
use App\Models\User;

class NotificationDeliveryLogger
{
    public function logSent(
        string $eventKey,
        ?User $recipient,
        string $subject,
        string $messagePreview,
        array $payload = [],
        string $channel = 'email',
        ?string $recipientAddress = null,
    ): NotificationDelivery
    {
        return NotificationDelivery::query()->create([
            'event_key' => $eventKey,
            'notifiable_type' => $recipient ? User::class : null,
            'notifiable_id' => $recipient?->id,
            'recipient_email' => $recipientAddress ?: $recipient?->email,
            'channel' => $channel,
            'status' => 'sent',
            'subject' => $subject,
            'message_preview' => $messagePreview,
            'payload' => $payload,
            'sent_at' => now(),
        ]);
    }

    public function logFailed(
        string $eventKey,
        ?User $recipient,
        string $subject,
        string $messagePreview,
        string $reason,
        array $payload = [],
        string $channel = 'email',
        ?string $recipientAddress = null,
    ): NotificationDelivery
    {
        return NotificationDelivery::query()->create([
            'event_key' => $eventKey,
            'notifiable_type' => $recipient ? User::class : null,
            'notifiable_id' => $recipient?->id,
            'recipient_email' => $recipientAddress ?: $recipient?->email,
            'channel' => $channel,
            'status' => 'failed',
            'subject' => $subject,
            'message_preview' => $messagePreview,
            'payload' => $payload,
            'failed_at' => now(),
            'failure_reason' => $reason,
            'retry_count' => 1,
        ]);
    }
}
