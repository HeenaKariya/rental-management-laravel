<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\ReminderNotificationService;
use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use App\Models\NotificationEventSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationCenterController extends Controller
{
    private const FILTER_SESSION_KEY_PREFIX = 'admin.notifications.filters.';

    public function index(): RedirectResponse
    {
        return to_route('admin.notifications.settings');
    }

    public function settings(): View
    {
        $eventDefaults = ReminderNotificationService::EVENTS;

        $settings = collect($eventDefaults)
            ->map(function (int $defaultLeadDays, string $eventKey) {
                $config = NotificationEventSetting::enabledFor($eventKey, $defaultLeadDays);

                return [
                    'event_key' => $eventKey,
                    'is_enabled' => $config['is_enabled'],
                    'email_enabled' => $config['email_enabled'],
                    'whatsapp_enabled' => $config['whatsapp_enabled'],
                    'lead_days' => $config['lead_days'],
                    'default_lead_days' => $defaultLeadDays,
                ];
            })
            ->sortBy('event_key')
            ->values();

        return view('admin.notifications.settings', [
            'settings' => $settings,
        ]);
    }

    public function deliveries(Request $request): View|RedirectResponse
    {
        return to_route('admin.notifications.deliveries.email');
    }

    public function emailDeliveries(Request $request): View|RedirectResponse
    {
        return $this->renderChannelDeliveries($request, 'email');
    }

    public function whatsappDeliveries(Request $request): View|RedirectResponse
    {
        return $this->renderChannelDeliveries($request, 'whatsapp');
    }

    private function renderChannelDeliveries(Request $request, string $channel): View|RedirectResponse
    {
        $sessionScope = $this->filterSessionScope($channel);

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionScope);

            return to_route($this->deliveriesRouteName($channel));
        }

        $eventDefaults = ReminderNotificationService::EVENTS;
        $filters = $this->resolveFilters($request, $sessionScope);
        $filterQuery = $this->filtersToQuery($filters);

        $deliveriesQuery = $this->filteredDeliveriesQuery($filters, $channel);

        $deliveries = (clone $deliveriesQuery)
            ->latest('id')
            ->paginate(25)
            ->appends($filterQuery);

        return view('admin.notifications.deliveries', [
            'currentChannel' => $channel,
            'filters' => $filters,
            'filterQuery' => $filterQuery,
            'deliveries' => $deliveries,
            'eventKeys' => array_keys($eventDefaults),
            'summary' => $this->summary((clone $deliveriesQuery)->get()),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        return $this->exportChannelCsv($request, 'email');
    }

    public function exportChannelCsv(Request $request, string $channel): StreamedResponse
    {
        $channel = $this->normalizeChannel($channel);

        $filters = $this->resolveFilters($request, $this->filterSessionScope($channel));

        $rows = $this->filteredDeliveriesQuery($filters, $channel)
            ->latest('id')
            ->get();

        return response()->streamDownload(function () use ($rows, $filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report', 'Notification Delivery Log']);
            fputcsv($handle, ['Status filter', $filters['status'] === 'all' ? 'All statuses' : str($filters['status'])->title()->toString()]);
            fputcsv($handle, ['Event filter', $filters['event_key'] ?: 'All events']);
            fputcsv($handle, ['Date from', $filters['date_from'] ?? '']);
            fputcsv($handle, ['Date to', $filters['date_to'] ?? '']);
            fputcsv($handle, ['Recipient contains', $filters['recipient'] ?? '']);
            fputcsv($handle, []);

            fputcsv($handle, ['Event', 'Recipient', 'Status', 'Channel', 'Retries', 'Sent At', 'Failed At', 'Failure Reason', 'Subject']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->event_key,
                    $row->recipient_email ?: ($row->notifiable?->email ?: ''),
                    $row->status,
                    $row->channel,
                    (int) $row->retry_count,
                    $row->sent_at?->format('Y-m-d H:i:s'),
                    $row->failed_at?->format('Y-m-d H:i:s'),
                    $row->failure_reason,
                    $row->subject,
                ]);
            }

            fclose($handle);
        }, 'notification-deliveries-'.$channel.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'events' => ['required', 'array'],
            'events.*.is_enabled' => ['nullable', 'boolean'],
            'events.*.email_enabled' => ['nullable', 'boolean'],
            'events.*.whatsapp_enabled' => ['nullable', 'boolean'],
            'events.*.lead_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        foreach (ReminderNotificationService::EVENTS as $eventKey => $defaultLeadDays) {
            $row = $payload['events'][$eventKey] ?? [];
            $eventEnabled = isset($row['is_enabled']) ? (bool) $row['is_enabled'] : false;

            NotificationEventSetting::query()->updateOrCreate(
                ['event_key' => $eventKey],
                [
                    'is_enabled' => $eventEnabled,
                    'email_enabled' => $eventEnabled && (isset($row['email_enabled']) ? (bool) $row['email_enabled'] : false),
                    'whatsapp_enabled' => $eventEnabled && (isset($row['whatsapp_enabled']) ? (bool) $row['whatsapp_enabled'] : false),
                    'lead_days' => isset($row['lead_days']) ? (int) $row['lead_days'] : $defaultLeadDays,
                ]
            );
        }

        return to_route('admin.notifications.settings')->with('status', 'Notification trigger settings updated.');
    }

    public function dispatchNow(ReminderNotificationService $service): RedirectResponse
    {
        $channel = $this->normalizeChannel(request()->input('delivery_channel'));
        $result = $service->dispatch(today: null, channel: $channel);
        $channelLabel = $channel === 'whatsapp' ? 'WhatsApp' : 'Email';

        return to_route($this->deliveriesRouteName($channel))
            ->with('status', $channelLabel.' dispatch complete. Sent '.$result['sent'].' and failed '.$result['failed'].'.');
    }

    public function retryFailed(ReminderNotificationService $service): RedirectResponse
    {
        $channel = $this->normalizeChannel(request()->input('delivery_channel'));
        $result = $service->retryFailed($channel);
        $channelLabel = $channel === 'whatsapp' ? 'WhatsApp' : 'Email';

        return to_route($this->deliveriesRouteName($channel))
            ->with('status', $channelLabel.' retry complete. Retried '.$result['retried'].' and resolved '.$result['resolved'].'.');
    }

    public function retryOne(ReminderNotificationService $service, NotificationDelivery $delivery): RedirectResponse
    {
        $resolved = $service->retryDelivery($delivery);
        $channel = $this->normalizeChannel(request()->input('delivery_channel') ?: $delivery->channel);

        return to_route($this->deliveriesRouteName($channel))
            ->with('status', $resolved ? 'Delivery retried successfully.' : 'Retry failed. Recipient email is still missing.');
    }

    private function summary(Collection $deliveries): array
    {
        return [
            'total' => $deliveries->count(),
            'sent' => $deliveries->where('status', 'sent')->count(),
            'failed' => $deliveries->where('status', 'failed')->count(),
            'pending' => $deliveries->where('status', 'pending')->count(),
        ];
    }

    private function normalizeFilters(Request $request): array
    {
        $payload = $request->validate([
            'status' => ['nullable', 'string', 'in:all,pending,sent,failed'],
            'event_key' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'recipient' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'status' => $payload['status'] ?? 'all',
            'event_key' => $payload['event_key'] ?? null,
            'date_from' => $payload['date_from'] ?? null,
            'date_to' => $payload['date_to'] ?? null,
            'recipient' => $payload['recipient'] ?? null,
        ];
    }

    private function resolveFilters(Request $request, string $sessionScope): array
    {
        $hasFilterInput = collect(['status', 'event_key', 'date_from', 'date_to', 'recipient'])
            ->contains(fn (string $key) => $request->has($key));

        if ($hasFilterInput) {
            $filters = $this->normalizeFilters($request);
            $request->session()->put($sessionScope, $filters);

            return $filters;
        }

        $stored = $request->session()->get($sessionScope);

        if (is_array($stored)) {
            return array_merge([
                'status' => 'all',
                'event_key' => null,
                'date_from' => null,
                'date_to' => null,
                'recipient' => null,
            ], $stored);
        }

        return [
            'status' => 'all',
            'event_key' => null,
            'date_from' => null,
            'date_to' => null,
            'recipient' => null,
        ];
    }

    private function filtersToQuery(array $filters): array
    {
        $query = [];

        if (($filters['status'] ?? 'all') !== 'all') {
            $query['status'] = $filters['status'];
        }

        if (filled($filters['event_key'] ?? null)) {
            $query['event_key'] = $filters['event_key'];
        }

        if (filled($filters['date_from'] ?? null)) {
            $query['date_from'] = $filters['date_from'];
        }

        if (filled($filters['date_to'] ?? null)) {
            $query['date_to'] = $filters['date_to'];
        }

        if (filled($filters['recipient'] ?? null)) {
            $query['recipient'] = $filters['recipient'];
        }

        return $query;
    }

    private function filteredDeliveriesQuery(array $filters, string $channel)
    {
        return NotificationDelivery::query()
            ->with('notifiable')
            ->where('channel', $this->normalizeChannel($channel))
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                filled($filters['event_key']),
                fn ($query) => $query->where('event_key', $filters['event_key'])
            )
            ->when(
                filled($filters['date_from']),
                fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to']),
                fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to'])
            )
            ->when(
                filled($filters['recipient']),
                fn ($query) => $query->where('recipient_email', 'like', '%'.$filters['recipient'].'%')
            );
    }

    private function normalizeChannel(?string $channel): string
    {
        return in_array($channel, ['email', 'whatsapp'], true) ? $channel : 'email';
    }

    private function deliveriesRouteName(string $channel): string
    {
        return $this->normalizeChannel($channel) === 'whatsapp'
            ? 'admin.notifications.deliveries.whatsapp'
            : 'admin.notifications.deliveries.email';
    }

    private function filterSessionScope(string $channel): string
    {
        return self::FILTER_SESSION_KEY_PREFIX.$this->normalizeChannel($channel);
    }
}
