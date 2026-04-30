<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Notifications\InvitationIssuedNotification;
use App\Domain\Notifications\Contracts\WhatsappNotificationGateway;
use App\Domain\Notifications\NotificationDeliveryLogger;
use App\Models\Invitation;
use App\Models\NotificationEventSetting;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class InvitationController extends Controller
{
    public function create(): View
    {
        return view('auth.invitations.create', [
            'roles' => Role::query()
                ->where('slug', '!=', 'super_admin')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::exists(Role::class, 'slug')],
        ]);

        $roleId = Role::query()->where('slug', $validated['role'])->value('id');

        $invitation = Invitation::issue([
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role_id' => $roleId,
            'invited_by' => $request->user()->id,
        ]);

        $this->dispatchInvitationNotification($invitation);

        return redirect()
            ->route('invitations.create')
            ->with('status', 'Invitation created for '.$invitation->email)
            ->with('invitation_url', route('invitations.accept', $invitation->token));
    }

    public function accept(string $token): RedirectResponse
    {
        return redirect()->route('register', ['invite' => $token]);
    }

    private function dispatchInvitationNotification(Invitation $invitation): void
    {
        $settings = NotificationEventSetting::enabledFor('user_invitation_issued', 0);

        if (! $settings['is_enabled']) {
            return;
        }

        /** @var NotificationDeliveryLogger $logger */
        $logger = app(NotificationDeliveryLogger::class);
        $invitationUrl = route('invitations.accept', $invitation->token);
        $subject = 'Your PropMgr invitation';
        $messagePreview = 'You have been invited to PropMgr. Accept invite: '.$invitationUrl;
        $payload = [
            'event' => 'user_invitation_issued',
            'invitation_id' => $invitation->id,
            'role_id' => $invitation->role_id,
        ];

        if ((bool) ($settings['email_enabled'] ?? true)) {
            try {
                Notification::route('mail', $invitation->email)
                    ->notify(new InvitationIssuedNotification($invitation->loadMissing('role'), $invitationUrl));

                $logger->logSent(
                    'user_invitation_issued',
                    null,
                    $subject,
                    $messagePreview,
                    $payload,
                    'email',
                    $invitation->email,
                );
            } catch (Throwable $exception) {
                $logger->logFailed(
                    'user_invitation_issued',
                    null,
                    $subject,
                    $messagePreview,
                    'Email delivery failed: '.$exception->getMessage(),
                    $payload,
                    'email',
                    $invitation->email,
                );
            }
        }

        if ((bool) ($settings['whatsapp_enabled'] ?? false)) {
            if (blank($invitation->phone)) {
                $logger->logFailed(
                    'user_invitation_issued',
                    null,
                    $subject,
                    $messagePreview,
                    'Recipient phone is missing for WhatsApp notification.',
                    $payload,
                    'whatsapp',
                    $invitation->phone,
                );

                return;
            }

            try {
                app(WhatsappNotificationGateway::class)->send(
                    (string) $invitation->phone,
                    'You have been invited to PropMgr. Use this link to continue: '.$invitationUrl
                );

                $logger->logSent(
                    'user_invitation_issued',
                    null,
                    $subject,
                    $messagePreview,
                    $payload,
                    'whatsapp',
                    (string) $invitation->phone,
                );
            } catch (Throwable $exception) {
                $logger->logFailed(
                    'user_invitation_issued',
                    null,
                    $subject,
                    $messagePreview,
                    'WhatsApp delivery failed: '.$exception->getMessage(),
                    $payload,
                    'whatsapp',
                    (string) $invitation->phone,
                );
            }
        }
    }
}
