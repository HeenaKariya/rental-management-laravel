<?php

namespace App\Domain\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected readonly string $code,
        protected readonly string $purpose,
        protected readonly int $ttlMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->purpose === 'setup_confirmation'
            ? 'Confirm your PropMgr two-factor setup'
            : 'Your PropMgr login verification code';

        return (new MailMessage)
            ->subject($subject)
            ->line('Use the following one-time password to continue:')
            ->line($this->code)
            ->line('This code expires in '.$this->ttlMinutes.' minutes.')
            ->line('If you did not request this code, ignore this message and contact a Super Admin.');
    }
}
