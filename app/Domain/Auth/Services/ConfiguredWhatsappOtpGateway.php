<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\WhatsappOtpGateway;
use App\Domain\Notifications\Contracts\WhatsappNotificationGateway;

class ConfiguredWhatsappOtpGateway implements WhatsappOtpGateway
{
    public function __construct(
        protected readonly WhatsappNotificationGateway $whatsappGateway,
    ) {}

    public function send(string $phone, string $message): void
    {
        $this->whatsappGateway->send($phone, $message);
    }
}
