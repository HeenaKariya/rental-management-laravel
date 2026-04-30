<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\WhatsappNotificationGateway;
use Illuminate\Support\Facades\Log;

class LogWhatsappNotificationGateway implements WhatsappNotificationGateway
{
    public function send(string $phone, string $message): void
    {
        Log::channel(config('notifications.whatsapp.log_channel', 'stack'))
            ->info('WhatsApp notification dispatched.', [
                'phone' => $phone,
                'message' => $message,
            ]);
    }
}
