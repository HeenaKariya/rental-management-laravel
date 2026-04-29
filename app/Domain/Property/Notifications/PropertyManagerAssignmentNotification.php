<?php

namespace App\Domain\Property\Notifications;

use App\Models\Property;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PropertyManagerAssignmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected readonly Property $property,
        protected readonly string $action,
        protected readonly ?User $actor = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $actorName = $this->actor?->name ?? 'A PropMgr administrator';

        if ($this->action === 'revoked') {
            return (new MailMessage)
                ->subject('Your PropMgr property assignment was removed')
                ->line('Your manager access to "'.$this->property->title.'" has been removed.')
                ->line('Updated by: '.$actorName)
                ->line('If you believe this change is incorrect, contact your Super Admin.');
        }

        return (new MailMessage)
            ->subject('You were assigned to a property in PropMgr')
            ->line('You now have manager access to "'.$this->property->title.'".')
            ->line('Assigned by: '.$actorName)
            ->action('Open property', route('properties.show', $this->property))
            ->line('Use your PropMgr workspace to review the property details and activity.');
    }
}