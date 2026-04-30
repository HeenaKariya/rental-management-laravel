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
    private const FILTER_SESSION_KEY = 'admin.notifications.filters';

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->boolean('reset')) {
            $request->session()->forget(self::FILTER_SESSION_KEY);

            return to_route('admin.notifications.index');
        }

        $eventDefaults = ReminderNotificationService::EVENTS;
        $filters = $this->resolveFilters($request);
        $filterQuery = $this->filtersToQuery($filters);

        $settings = collect($eventDefaults)
            ->map(function (int $defaultLeadDays, string $eventKey) {
                $config = NotificationEventSetting::enabledFor($eventKey, $defaultLeadDays);

                return [
                    'event_key' => $eventKey,
                    'is_enabled' => $config['is_enabled'],
                    'lead_days' => $config['lead_days'],
                    'default_lead_days' => $defaultLeadDays,
                ];
            })
            ->sortBy('event_key')
            ->values();

        $deliveriesQuery = $this->filteredDeliveriesQuery($filters);

        $deliveries = (clone $deliveriesQuery)
            ->latest('id')
            ->paginate(25)
            ->appends($filterQuery);

        return view('admin.notifications.index', [
            'filters' => $filters,
            'filterQuery' => $filterQuery,
            'deliveries' => $deliveries,
            'eventKeys' => array_keys($eventDefaults),
            'settings' => $settings,
            'summary' => $this->summary((clone $deliveriesQuery)->get()),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);

        $rows = $this->filteredDeliveriesQuery($filters)
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
        }, 'notification-deliveries.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'events' => ['required', 'array'],
            'events.*.is_enabled' => ['nullable', 'boolean'],
            'events.*.lead_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        foreach (ReminderNotificationService::EVENTS as $eventKey => $defaultLeadDays) {
            $row = $payload['events'][$eventKey] ?? [];

            NotificationEventSetting::query()->updateOrCreate(
                ['event_key' => $eventKey],
                [
                    'is_enabled' => isset($row['is_enabled']) ? (bool) $row['is_enabled'] : false,
                    'lead_days' => isset($row['lead_days']) ? (int) $row['lead_days'] : $defaultLeadDays,
                ]
            );
        }

        return to_route('admin.notifications.index')->with('status', 'Notification trigger settings updated.');
    }

    public function dispatchNow(ReminderNotificationService $service): RedirectResponse
    {
        $result = $service->dispatch();

        return to_route('admin.notifications.index')
            ->with('status', 'Dispatch run complete. Sent '.$result['sent'].' and failed '.$result['failed'].'.');
    }

    public function retryFailed(ReminderNotificationService $service): RedirectResponse
    {
        $result = $service->retryFailed();

        return to_route('admin.notifications.index')
            ->with('status', 'Retry run complete. Retried '.$result['retried'].' and resolved '.$result['resolved'].'.');
    }

    public function retryOne(ReminderNotificationService $service, NotificationDelivery $delivery): RedirectResponse
    {
        $resolved = $service->retryDelivery($delivery);

        return to_route('admin.notifications.index')
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

    private function resolveFilters(Request $request): array
    {
        $hasFilterInput = collect(['status', 'event_key', 'date_from', 'date_to', 'recipient'])
            ->contains(fn (string $key) => $request->has($key));

        if ($hasFilterInput) {
            $filters = $this->normalizeFilters($request);
            $request->session()->put(self::FILTER_SESSION_KEY, $filters);

            return $filters;
        }

        $stored = $request->session()->get(self::FILTER_SESSION_KEY);

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

    private function filteredDeliveriesQuery(array $filters)
    {
        return NotificationDelivery::query()
            ->with('notifiable')
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
}
