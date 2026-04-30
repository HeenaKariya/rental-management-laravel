<?php

namespace App\Domain\Agreement\Notifications;

use App\Models\RentAgreement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignedAgreementCopyNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected readonly RentAgreement $agreement,
        protected readonly string $pdfBinary,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $leaseNumber = $this->agreement->lease?->lease_number ?? 'your lease';
        $fileName = sprintf('lease-agreement-%s.pdf', str_replace('/', '-', (string) $leaseNumber));

        return (new MailMessage)
            ->subject('Your signed lease agreement copy')
            ->line('Your lease agreement has been signed successfully.')
            ->line('A signed PDF copy is attached for your records.')
            ->attachData($this->pdfBinary, $fileName, [
                'mime' => 'application/pdf',
            ]);
    }
}
