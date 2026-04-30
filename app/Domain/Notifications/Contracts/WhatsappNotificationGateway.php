<?php

namespace App\Domain\Notifications\Contracts;

interface WhatsappNotificationGateway
{
    public function send(string $phone, string $message): void;
}
