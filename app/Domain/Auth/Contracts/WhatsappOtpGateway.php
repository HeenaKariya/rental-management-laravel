<?php

namespace App\Domain\Auth\Contracts;

interface WhatsappOtpGateway
{
    public function send(string $phone, string $message): void;
}
