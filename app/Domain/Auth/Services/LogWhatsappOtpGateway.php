<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\WhatsappOtpGateway;
use Illuminate\Support\Facades\Log;

class LogWhatsappOtpGateway implements WhatsappOtpGateway
{
    public function send(string $phone, string $message): void
    {
        Log::channel(config('auth-otp.whatsapp.log_channel', 'stack'))
            ->info('WhatsApp OTP dispatched.', [
                'message' => $message,
                'phone' => $phone,
            ]);
    }
}
