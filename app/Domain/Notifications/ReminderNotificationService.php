<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Contracts\WhatsappNotificationGateway;
use App\Models\Lease;
use App\Models\NotificationDelivery;
use App\Models\NotificationEventSetting;
use App\Models\PropertyLoan;
use App\Models\RentAgreement;
use App\Models\RentInstalment;
use App\Models\RentLedger;
use App\Models\RentReturn;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

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
        'maintenance_request_status_changed' => 0,
        'rent_overdue' => 0,
        'lease_expired' => 0,
        'partial_payment_received' => 0,
        'arrears_carried_forward' => 0,
        'instalment_receipt_generated' => 0,
        'agreement_signature_pending' => 7,
        'agreement_signed' => 0,
        'agreement_integrity_failed' => 0,
        'notarized_agreement_upload_pending' => 7,
        'user_invitation_issued' => 0,
    ];

    public function dispatch(?Carbon $today = null, ?string $channel = null): array
    {
        $today ??= now()->startOfDay();
        $channelFilter = in_array($channel, ['email', 'whatsapp'], true) ? $channel : null;

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
                $deliveries = $this->deliver($eventKey, $snapshot, $eventConfig, $channelFilter);

                foreach ($deliveries as $delivery) {
                    if ($delivery->status === 'sent') {
                        $sent++;
                    } else {
                        $failed++;
                    }
                }
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    public function retryFailed(?string $channel = null): array
    {
        $retried = 0;
        $resolved = 0;
        $channelFilter = in_array($channel, ['email', 'whatsapp'], true) ? $channel : null;

        $query = NotificationDelivery::query()
            ->where('status', 'failed')
            ->whereIn('channel', ['email', 'whatsapp']);

        if ($channelFilter) {
            $query->where('channel', $channelFilter);
        }

        $query
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
        $channel = (string) ($delivery->channel ?: 'email');

        if ($channel === 'whatsapp') {
            $phone = $recipient?->phone ?: $delivery->recipient_email;

            if (! filled($phone)) {
                $delivery->forceFill([
                    'retry_count' => ((int) $delivery->retry_count) + 1,
                    'failure_reason' => 'Retry failed: recipient phone is still missing.',
                    'failed_at' => now(),
                ])->save();

                return false;
            }

            try {
                app(WhatsappNotificationGateway::class)->send(
                    (string) $phone,
                    (string) ($delivery->message_preview ?: $delivery->subject ?: 'PropMgr notification')
                );

                $delivery->forceFill([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'failed_at' => null,
                    'failure_reason' => null,
                    'retry_count' => ((int) $delivery->retry_count) + 1,
                    'recipient_email' => $phone,
                ])->save();

                return true;
            } catch (Throwable $exception) {
                $delivery->forceFill([
                    'retry_count' => ((int) $delivery->retry_count) + 1,
                    'failure_reason' => 'Retry failed: '.$exception->getMessage(),
                    'failed_at' => now(),
                ])->save();

                return false;
            }
        }

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

    private function deliver(string $eventKey, array $snapshot, array $eventConfig, ?string $channelFilter = null): array
    {
        $logger = app(NotificationDeliveryLogger::class);
        $whatsappGateway = app(WhatsappNotificationGateway::class);

        /** @var User|null $recipient */
        $recipient = $snapshot['recipient'] ?? null;
        $subject = $snapshot['subject'] ?? 'PropMgr notification';
        $messagePreview = $snapshot['message_preview'] ?? '';
        $payload = $snapshot['payload'] ?? [];

        $deliveries = [];

        $allowEmail = $channelFilter === null || $channelFilter === 'email';
        $allowWhatsapp = $channelFilter === null || $channelFilter === 'whatsapp';

        if ($allowEmail && (bool) ($eventConfig['email_enabled'] ?? true)) {
            if (! $recipient || blank($recipient->email)) {
                $deliveries[] = $logger->logFailed(
                    $eventKey,
                    $recipient,
                    $subject,
                    $messagePreview,
                    'Recipient email is missing for this notification.',
                    $payload,
                    'email',
                );
            } else {
                $deliveries[] = $logger->logSent(
                    $eventKey,
                    $recipient,
                    $subject,
                    $messagePreview,
                    $payload,
                    'email',
                );
            }
        }

        if ($allowWhatsapp && (bool) ($eventConfig['whatsapp_enabled'] ?? false)) {
            $phone = $recipient?->phone;

            if (! $recipient || blank($phone)) {
                $deliveries[] = $logger->logFailed(
                    $eventKey,
                    $recipient,
                    $subject,
                    $messagePreview,
                    'Recipient phone is missing for WhatsApp notification.',
                    $payload,
                    'whatsapp',
                    $phone,
                );
            } else {
                try {
                    $whatsappGateway->send((string) $phone, $messagePreview ?: $subject);

                    $deliveries[] = $logger->logSent(
                        $eventKey,
                        $recipient,
                        $subject,
                        $messagePreview,
                        $payload,
                        'whatsapp',
                        (string) $phone,
                    );
                } catch (Throwable $exception) {
                    $deliveries[] = $logger->logFailed(
                        $eventKey,
                        $recipient,
                        $subject,
                        $messagePreview,
                        'WhatsApp delivery failed: '.$exception->getMessage(),
                        $payload,
                        'whatsapp',
                        (string) $phone,
                    );
                }
            }
        }

        if ($deliveries === [] && $channelFilter === null) {
            $deliveries[] = $logger->logFailed(
                $eventKey,
                $recipient,
                $subject,
                $messagePreview,
                'No channels are enabled for this notification event.',
                $payload,
                'email',
            );
        }

        return $deliveries;
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
            'rent_overdue' => $this->rentOverdueSnapshots($today),
            'lease_expired' => $this->leaseExpiredSnapshots($today),
            'partial_payment_received' => $this->partialPaymentSnapshots($today, $leadDays),
            'arrears_carried_forward' => $this->arrearsCarriedForwardSnapshots($today),
            'instalment_receipt_generated' => $this->instalmentReceiptSnapshots($today, $leadDays),
            'agreement_signature_pending' => $this->agreementSignaturePendingSnapshots($today, $leadDays),
            'agreement_signed' => $this->agreementSignedSnapshots($today, $leadDays),
            'agreement_integrity_failed' => $this->agreementIntegrityFailedSnapshots($today, $leadDays),
            'notarized_agreement_upload_pending' => $this->notarizedAgreementUploadPendingSnapshots($today, $leadDays),
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

    private function rentOverdueSnapshots(Carbon $today): array
    {
        $ledgers = RentLedger::query()
            ->with(['lease.tenant.user', 'lease.unit.property.activeManagerAssignments.manager'])
            ->where(function ($query) use ($today) {
                $query->where('status', 'overdue')
                    ->orWhere(function ($subQuery) use ($today) {
                        $subQuery
                            ->whereDate('due_on', '<', $today->toDateString())
                            ->where('outstanding_balance', '>', 0);
                    });
            })
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
                    'subject' => 'Rent overdue',
                    'message_preview' => 'Rent payment is overdue for lease '.$lease?->lease_number.'.',
                    'payload' => [
                        'event' => 'rent_overdue',
                        'lease_id' => $lease?->id,
                        'rent_ledger_id' => $ledger->id,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function leaseExpiredSnapshots(Carbon $today): array
    {
        $leases = Lease::query()
            ->with(['unit.property.activeManagerAssignments.manager'])
            ->where('status', 'active')
            ->whereDate('end_on', '<', $today->toDateString())
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
                    'subject' => 'Lease expired',
                    'message_preview' => 'Lease '.$lease->lease_number.' has expired.',
                    'payload' => [
                        'event' => 'lease_expired',
                        'lease_id' => $lease->id,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function partialPaymentSnapshots(Carbon $today, int $leadDays): array
    {
        $targetDate = $today->copy()->subDays($leadDays)->toDateString();

        $instalments = RentInstalment::query()
            ->with(['ledger.lease.tenant.user', 'ledger.lease.unit.property.activeManagerAssignments.manager'])
            ->whereNull('voided_at')
            ->whereDate('payment_date', $targetDate)
            ->whereHas('ledger', fn ($query) => $query->where('status', 'partially_paid'))
            ->get();

        $snapshots = [];

        foreach ($instalments as $instalment) {
            $ledger = $instalment->ledger;
            $lease = $ledger?->lease;
            $property = $lease?->unit?->property;

            $recipients = $this->collectRecipients(
                [$lease?->tenant?->user],
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Partial payment received',
                    'message_preview' => 'A partial rent payment was recorded for lease '.$lease?->lease_number.'.',
                    'payload' => [
                        'event' => 'partial_payment_received',
                        'rent_instalment_id' => $instalment->id,
                        'rent_ledger_id' => $ledger?->id,
                        'lease_id' => $lease?->id,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function arrearsCarriedForwardSnapshots(Carbon $today): array
    {
        $ledgers = RentLedger::query()
            ->with(['lease.tenant.user', 'lease.unit.property.activeManagerAssignments.manager'])
            ->whereDate('payment_month', $today->copy()->startOfMonth()->toDateString())
            ->where('carried_arrears', '>', 0)
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
                    'subject' => 'Arrears carried forward',
                    'message_preview' => 'Arrears were carried forward for lease '.$lease?->lease_number.'.',
                    'payload' => [
                        'event' => 'arrears_carried_forward',
                        'lease_id' => $lease?->id,
                        'rent_ledger_id' => $ledger->id,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function instalmentReceiptSnapshots(Carbon $today, int $leadDays): array
    {
        $targetDate = $today->copy()->subDays($leadDays)->toDateString();

        $instalments = RentInstalment::query()
            ->with(['ledger.lease.tenant.user'])
            ->whereNull('voided_at')
            ->whereDate('payment_date', $targetDate)
            ->get();

        $snapshots = [];

        foreach ($instalments as $instalment) {
            $lease = $instalment->ledger?->lease;
            $tenantUser = $lease?->tenant?->user;

            if (! $tenantUser) {
                continue;
            }

            $snapshots[] = [
                'recipient' => $tenantUser,
                'subject' => 'Instalment receipt generated',
                'message_preview' => 'Your rent instalment receipt is available for lease '.$lease?->lease_number.'.',
                'payload' => [
                    'event' => 'instalment_receipt_generated',
                    'rent_instalment_id' => $instalment->id,
                    'rent_ledger_id' => $instalment->rent_ledger_id,
                    'lease_id' => $lease?->id,
                ],
            ];
        }

        return $snapshots;
    }

    private function agreementSignaturePendingSnapshots(Carbon $today, int $leadDays): array
    {
        $thresholdDate = $today->copy()->subDays($leadDays)->toDateString();

        $agreements = RentAgreement::query()
            ->with(['lease.tenant.user', 'lease.unit.property.activeManagerAssignments.manager'])
            ->whereIn('status', ['generated', 'viewed'])
            ->whereDate('created_at', '<=', $thresholdDate)
            ->get();

        $snapshots = [];

        foreach ($agreements as $agreement) {
            $lease = $agreement->lease;
            $property = $lease?->unit?->property;

            $recipients = $this->collectRecipients(
                [$lease?->tenant?->user],
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
                $this->superAdmins()->all(),
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Agreement signature pending',
                    'message_preview' => 'Lease agreement '.$agreement->id.' is still pending signature.',
                    'payload' => [
                        'event' => 'agreement_signature_pending',
                        'agreement_id' => $agreement->id,
                        'lease_id' => $lease?->id,
                        'status' => $agreement->status,
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function agreementSignedSnapshots(Carbon $today, int $leadDays): array
    {
        $targetDate = $today->copy()->subDays($leadDays)->toDateString();

        $agreements = RentAgreement::query()
            ->with(['lease.tenant.user', 'lease.unit.property.activeManagerAssignments.manager'])
            ->where('status', 'signed')
            ->whereNotNull('signed_at')
            ->whereDate('signed_at', $targetDate)
            ->get();

        $snapshots = [];

        foreach ($agreements as $agreement) {
            $lease = $agreement->lease;
            $property = $lease?->unit?->property;

            $recipients = $this->collectRecipients(
                [$lease?->tenant?->user],
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
                $this->superAdmins()->all(),
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Agreement signed',
                    'message_preview' => 'Lease agreement '.$agreement->id.' was signed.',
                    'payload' => [
                        'event' => 'agreement_signed',
                        'agreement_id' => $agreement->id,
                        'lease_id' => $lease?->id,
                        'signed_at' => $agreement->signed_at?->toDateTimeString(),
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function agreementIntegrityFailedSnapshots(Carbon $today, int $leadDays): array
    {
        $targetDate = $today->copy()->subDays($leadDays)->toDateString();

        $agreements = RentAgreement::query()
            ->with(['lease.unit.property.activeManagerAssignments.manager'])
            ->where('integrity_check_status', 'tampered')
            ->whereNotNull('integrity_last_checked_at')
            ->whereDate('integrity_last_checked_at', $targetDate)
            ->get();

        $snapshots = [];

        foreach ($agreements as $agreement) {
            $lease = $agreement->lease;
            $property = $lease?->unit?->property;

            $recipients = $this->collectRecipients(
                $property?->activeManagerAssignments?->pluck('manager')->all() ?? [],
                $this->superAdmins()->all(),
            );

            foreach ($recipients as $recipient) {
                $snapshots[] = [
                    'recipient' => $recipient,
                    'subject' => 'Agreement integrity check failed',
                    'message_preview' => 'Agreement '.$agreement->id.' failed integrity verification.',
                    'payload' => [
                        'event' => 'agreement_integrity_failed',
                        'agreement_id' => $agreement->id,
                        'lease_id' => $lease?->id,
                        'checked_at' => $agreement->integrity_last_checked_at?->toDateTimeString(),
                    ],
                ];
            }
        }

        return $snapshots;
    }

    private function notarizedAgreementUploadPendingSnapshots(Carbon $today, int $leadDays): array
    {
        $thresholdDate = $today->copy()->subDays($leadDays)->toDateString();

        $leases = Lease::query()
            ->with(['unit.property.activeManagerAssignments.manager'])
            ->where('status', 'active')
            ->whereDate('start_on', '<=', $thresholdDate)
            ->whereDoesntHave('notarizedAgreements')
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
                    'subject' => 'Notarized agreement upload pending',
                    'message_preview' => 'Lease '.$lease->lease_number.' is pending notarized agreement upload.',
                    'payload' => [
                        'event' => 'notarized_agreement_upload_pending',
                        'lease_id' => $lease->id,
                        'start_on' => $lease->start_on?->toDateString(),
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
