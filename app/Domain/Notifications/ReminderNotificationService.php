<?php

namespace App\Domain\Notifications;

use App\Models\Lease;
use App\Models\NotificationDelivery;
use App\Models\NotificationEventSetting;
use App\Models\PropertyLoan;
use App\Models\RentLedger;
use App\Models\RentReturn;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReminderNotificationService
{
    public const EVENTS = [
        'rent_due_reminder' => 3,
        'lease_expiring_soon' => 30,
        'kyc_pending' => 0,
        'emi_due_reminder' => 3,
        'deposit_collection_pending' => 7,
        'deposit_refund_overdue' => 14,
        'rent_return_pending_settlement_overdue' => 7,
    ];

    public function dispatch(?Carbon $today = null): array
    {
        $today ??= now()->startOfDay();

        $sent = 0;
        $failed = 0;

        foreach (self::EVENTS as $eventKey => $defaultLeadDays) {
            $eventConfig = NotificationEventSetting::enabledFor($eventKey, $defaultLeadDays);

            if (! $eventConfig['is_enabled']) {
                continue;
            }

            $leadDays = (int) $eventConfig['lead_days'];
            $snapshots = $this->snapshotForEvent($eventKey, $today, $leadDays);

            foreach ($snapshots as $snapshot) {
                $delivery = $this->deliver($eventKey, $snapshot);

                if ($delivery->status === 'sent') {
                    $sent++;
                } else {
                    $failed++;
                }
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    public function retryFailed(): array
    {
        $retried = 0;
        $resolved = 0;

        NotificationDelivery::query()
            ->where('status', 'failed')
            ->where('channel', 'email')
            ->orderBy('id')
            ->chunkById(100, function (Collection $deliveries) use (&$retried, &$resolved): void {
                foreach ($deliveries as $delivery) {
                    if ($this->retryDelivery($delivery)) {
                        $resolved++;
                    }

                    $retried++;
                }
            });

        return [
            'retried' => $retried,
            'resolved' => $resolved,
        ];
    }

    public function retryDelivery(NotificationDelivery $delivery): bool
    {
        $recipient = $delivery->notifiable instanceof User ? $delivery->notifiable : null;
        $recipientEmail = $recipient?->email ?: $delivery->recipient_email;

        if (filled($recipientEmail)) {
            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
                'retry_count' => ((int) $delivery->retry_count) + 1,
                'recipient_email' => $recipientEmail,
            ])->save();

            return true;
        }

        $delivery->forceFill([
            'retry_count' => ((int) $delivery->retry_count) + 1,
            'failure_reason' => 'Retry failed: recipient email is still missing.',
            'failed_at' => now(),
        ])->save();

        return false;
    }

    private function deliver(string $eventKey, array $snapshot): NotificationDelivery
    {
        $logger = app(NotificationDeliveryLogger::class);

        /** @var User|null $recipient */
        $recipient = $snapshot['recipient'] ?? null;
        $subject = $snapshot['subject'] ?? 'PropMgr notification';
        $messagePreview = $snapshot['message_preview'] ?? '';
        $payload = $snapshot['payload'] ?? [];

        if (! $recipient || blank($recipient->email)) {
            return $logger->logFailed(
                $eventKey,
                $recipient,
                $subject,
                $messagePreview,
                'Recipient email is missing for this notification.',
                $payload,
            );
        }

        return $logger->logSent($eventKey, $recipient, $subject, $messagePreview, $payload);
    }

    private function snapshotForEvent(string $eventKey, Carbon $today, int $leadDays): array
    {
        return match ($eventKey) {
            'rent_due_reminder' => $this->rentDueSnapshots($today, $leadDays),
            'lease_expiring_soon' => $this->leaseExpiringSnapshots($today, $leadDays),
            'kyc_pending' => $this->kycPendingSnapshots(),
            'emi_due_reminder' => $this->emiDueSnapshots($today, $leadDays),
            'deposit_collection_pending' => $this->depositCollectionPendingSnapshots($today, $leadDays),
            'deposit_refund_overdue' => $this->depositRefundOverdueSnapshots($today, $leadDays),
            'rent_return_pending_settlement_overdue' => $this->rentReturnPendingSnapshots($today, $leadDays),
            default => [],
        };
    }

    private function rentDueSnapshots(Carbon $today, int $leadDays): array
    {
        $targetDate = $today->copy()->addDays($leadDays)->toDateString();

        $ledgers = RentLedger::query()
            ->with(['lease.tenant.user', 'lease.unit.property.activeManagerAssignments.manager'])
            ->whereDate('due_on', $targetDate)
            ->where('outstanding_balance', '>', 0)
            ->get();

        $snapshots = [];

        foreach ($ledgers as $ledger) {
            $lease = $ledger->lease;
            $property = $lease?->unit?->property;

            $recipients = $this->collectRecipients(
                [$lease?->tenant?->user],
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
                $this->superAdmins()->all(),
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Rent due reminder',
                    'message_preview' => 'Rent is due on '.$ledger->due_on->toDateString().' for lease '.$lease?->lease_number.'.',
                    'payload' => [
                        'event' => 'rent_due_reminder',
                        'due_on' => $ledger->due_on->toDateString(),
                        'lease_id' => $lease?->id,
                        'rent_ledger_id' => $ledger->id,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function leaseExpiringSnapshots(Carbon $today, int $leadDays): array
    {
        $targetDate = $today->copy()->addDays($leadDays)->toDateString();

        $leases = Lease::query()
            ->with(['unit.property.activeManagerAssignments.manager', 'unit.property.owners.user'])
            ->where('status', 'active')
            ->whereDate('end_on', $targetDate)
            ->get();

        $snapshots = [];

        foreach ($leases as $lease) {
            $property = $lease->unit?->property;

            $ownerUsers = $property?->owners
                ? $property->owners->where('is_active', true)->pluck('user')->filter()->values()->all()
                : [];

            $recipients = $this->collectRecipients(
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
                $this->superAdmins()->all(),
                $ownerUsers,
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Lease expiring soon',
                    'message_preview' => 'Lease '.$lease->lease_number.' is expiring on '.$lease->end_on->toDateString().'.',
                    'payload' => [
                        'event' => 'lease_expiring_soon',
                        'lease_id' => $lease->id,
                        'end_on' => $lease->end_on->toDateString(),
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function kycPendingSnapshots(): array
    {
        $tenants = Tenant::query()
            ->with(['user', 'unit.property.activeManagerAssignments.manager'])
            ->whereIn('kyc_status', ['pending', 'rejected'])
            ->get();

        $snapshots = [];

        foreach ($tenants as $tenant) {
            $property = $tenant->unit?->property;
            $recipients = $this->collectRecipients(
                [$tenant->user],
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'KYC pending action',
                    'message_preview' => 'KYC is '.$tenant->kyc_status.' for tenant '.$tenant->full_name.'.',
                    'payload' => [
                        'event' => 'kyc_pending',
                        'tenant_id' => $tenant->id,
                        'kyc_status' => $tenant->kyc_status,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function emiDueSnapshots(Carbon $today, int $leadDays): array
    {
        $targetDate = $today->copy()->addDays($leadDays);
        $targetDay = (int) $targetDate->day;

        $loans = PropertyLoan::query()
            ->with('property')
            ->where('emi_due_day', $targetDay)
            ->get();

        $snapshots = [];

        foreach ($loans as $loan) {
            foreach ($this->superAdmins() as $superAdmin) {
                $snapshots[] = [
                    'recipient' => $superAdmin,
                    'subject' => 'EMI due reminder',
                    'message_preview' => 'EMI for '.$loan->property?->title.' is due on day '.$targetDay.'.',
                    'payload' => [
                        'event' => 'emi_due_reminder',
                        'loan_id' => $loan->id,
                        'property_id' => $loan->property_id,
                        'emi_due_day' => $targetDay,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function depositCollectionPendingSnapshots(Carbon $today, int $leadDays): array
    {
        $threshold = $today->copy()->subDays($leadDays)->toDateString();

        $leases = Lease::query()
            ->with(['deposit', 'unit.property.activeManagerAssignments.manager'])
            ->where('status', 'active')
            ->whereDate('start_on', '<=', $threshold)
            ->where(function ($query) {
                $query->whereDoesntHave('deposit')
                    ->orWhereHas('deposit', fn ($depositQuery) => $depositQuery->where('collected_total', '<', 'expected_amount'));
            })
            ->get();

        $snapshots = [];

        foreach ($leases as $lease) {
            $property = $lease->unit?->property;
            $recipients = $this->collectRecipients(
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
                $this->superAdmins()->all(),
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Deposit collection pending',
                    'message_preview' => 'Deposit collection is still pending for lease '.$lease->lease_number.'.',
                    'payload' => [
                        'event' => 'deposit_collection_pending',
                        'lease_id' => $lease->id,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function depositRefundOverdueSnapshots(Carbon $today, int $leadDays): array
    {
        $threshold = $today->copy()->subDays($leadDays);

        $leases = Lease::query()
            ->with(['deposit', 'unit.property.activeManagerAssignments.manager'])
            ->where('status', 'terminated')
            ->whereNotNull('terminated_at')
            ->where('terminated_at', '<=', $threshold)
            ->whereHas('deposit', fn ($depositQuery) => $depositQuery->where('current_balance', '>', 0))
            ->get();

        $snapshots = [];

        foreach ($leases as $lease) {
            $property = $lease->unit?->property;
            $recipients = $this->collectRecipients(
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
                $this->superAdmins()->all(),
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Deposit refund overdue',
                    'message_preview' => 'Deposit refund is overdue for lease '.$lease->lease_number.'.',
                    'payload' => [
                        'event' => 'deposit_refund_overdue',
                        'lease_id' => $lease->id,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function rentReturnPendingSnapshots(Carbon $today, int $leadDays): array
    {
        $threshold = $today->copy()->subDays($leadDays);

        $returns = RentReturn::query()
            ->with(['lease.unit.property.activeManagerAssignments.manager'])
            ->where('status', 'pending_settlement')
            ->where('updated_at', '<=', $threshold)
            ->get();

        $snapshots = [];

        foreach ($returns as $rentReturn) {
            $property = $rentReturn->lease?->unit?->property;
            $recipients = $this->collectRecipients(
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
                $this->superAdmins()->all(),
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Rent return pending settlement overdue',
                    'message_preview' => 'Rent return for lease '.$rentReturn->lease?->lease_number.' is still pending settlement.',
                    'payload' => [
                        'event' => 'rent_return_pending_settlement_overdue',
                        'rent_return_id' => $rentReturn->id,
                        'lease_id' => $rentReturn->lease_id,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function superAdmins(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->get();
    }

    private function collectRecipients(...$groups): Collection
    {
        return collect($groups)
            ->flatten(1)
            ->filter(fn ($recipient) => $recipient instanceof User)
            ->unique('id')
            ->values();
    }
}
