<?php

namespace App\Domain\Auth\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected readonly Invitation $invitation,
        protected readonly string $invitationUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleName = $this->invitation->role?->name ?? 'PropMgr user';

        return (new MailMessage)
            ->subject('Your PropMgr invitation')
            ->line('You have been invited to join PropMgr as '.$roleName.'.')
            ->line('This invitation link expires on '.$this->invitation->expires_at?->format('M j, Y g:i A').'.')
            ->action('Accept invitation', $this->invitationUrl)
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
